<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\TranscribeCallJob;
use App\Models\Call;
use App\Models\Contact;
use App\Services\CallRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallController extends Controller
{
    /**
     * List calls with optional filters for contact_id, transcript_status, and transcript search.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess('viewAny', Call::class);

        $query = Call::with(['contact:id,name,phone,email', 'user:id,name,role'])
            ->latest('started_at');

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->filled('status')) {
            $query->where('transcript_status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $query->searchTranscript($request->input('q'));
        }

        $calls = $query->paginate($request->integer('per_page', 25));

        $calls->getCollection()->transform(fn($call) => $this->formatCall($call));

        return response()->json($calls);
    }

    /**
     * Get a specific call record.
     */
    public function show(Call $call): JsonResponse
    {
        $this->authorizeAccess('view', $call);

        $call->load(['contact', 'user:id,name,role']);

        return response()->json([
            'success' => true,
            'call'    => $this->formatCall($call),
        ]);
    }

    /**
     * Create / initiate a new call session.
     * Generates a unique provider_call_sid and an expiring signed contact join link.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess('create', Call::class);

        $validated = $request->validate([
            'contact_id'   => 'nullable|string|exists:contacts,id',
            'direction'    => 'nullable|in:inbound,outbound',
            'from_number'  => 'nullable|string|max:50',
            'to_number'    => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $joinToken = Str::random(40);
        $providerCallSid = 'call_' . Str::random(24);

        $call = Call::create([
            'contact_id'            => $validated['contact_id'] ?? null,
            'user_id'               => $user?->id,
            'provider_call_sid'     => $providerCallSid,
            'direction'             => $validated['direction'] ?? 'outbound',
            'from_number'           => $validated['from_number'] ?? ($user?->name ?: 'Office'),
            'to_number'             => $validated['to_number'] ?? null,
            'started_at'            => now(),
            'transcript_status'     => 'pending',
            'join_token'            => $joinToken,
            'join_token_expires_at' => now()->addHours(2),
        ]);

        $joinUrl = url("/call/join/{$joinToken}");

        return response()->json([
            'success'  => true,
            'call'     => $this->formatCall($call),
            'joinUrl'  => $joinUrl,
            'roomCode' => $providerCallSid,
        ], 201);
    }

    /**
     * Upload an audio recording for a call (from WebRTC MediaRecorder or manual upload).
     * Accepts: mp3, m4a, wav, webm, ogg, amr.
     */
    public function uploadRecording(
        Request $request,
        Call $call,
        CallRecordingService $recordingService
    ): JsonResponse {
        $this->authorizeAccess('create', $call);

        $maxKb = (int) config('services.whisper.max_upload_size_kb', 102400);

        $request->validate([
            'recording'        => "required|file|max:{$maxKb}",
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('recording');
        $duration = $request->input('duration_seconds') ? (int) $request->input('duration_seconds') : null;

        // Verify valid audio extension or MIME type
        $allowedExtensions = ['mp3', 'm4a', 'wav', 'webm', 'ogg', 'amr'];
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        $isAudioMime = str_starts_with($mime, 'audio/') || in_array($mime, [
            'video/webm', // MediaRecorder often labels audio webm as video/webm
            'application/octet-stream',
        ], true);

        if (!in_array($extension, $allowedExtensions, true) && !$isAudioMime) {
            return response()->json([
                'success' => false,
                'error'   => 'Unsupported audio file format. Allowed: mp3, m4a, wav, webm, ogg, amr.',
            ], 422);
        }

        $call = $recordingService->storeAndDispatch(
            $call,
            $file,
            $extension ?: 'webm',
            $duration
        );

        return response()->json([
            'success' => true,
            'message' => 'Recording uploaded successfully. Transcription queued.',
            'call'    => $this->formatCall($call),
        ]);
    }

    /**
     * Stream the recording file securely from private storage.
     */
    public function streamRecording(Request $request, Call $call)
    {
        // Allow access via valid signed route OR authenticated user with policy permission
        if (!$request->hasValidSignature()) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated or invalid signature.'], 401);
            }
            $this->authorizeAccess('viewRecording', $call);
        }

        if (empty($call->recording_path)) {
            return response()->json(['error' => 'Call has no recording.'], 404);
        }

        $disk = config('services.whisper.disk', 'local');
        if (!Storage::disk($disk)->exists($call->recording_path)) {
            return response()->json(['error' => 'Recording file not found on disk.'], 404);
        }

        $mimeType = Storage::disk($disk)->mimeType($call->recording_path) ?: 'audio/webm';
        $size = Storage::disk($disk)->size($call->recording_path);
        $stream = Storage::disk($disk)->readStream($call->recording_path);

        $headers = [
            'Content-Type'        => $mimeType,
            'Content-Length'      => $size,
            'Content-Disposition' => 'inline; filename="' . basename($call->recording_path) . '"',
            'Accept-Ranges'       => 'bytes',
            'Cache-Control'       => 'private, max-age=3600',
        ];

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Manually retry transcription for a failed or stuck call.
     */
    public function retryTranscription(Call $call): JsonResponse
    {
        $this->authorizeAccess('retryTranscription', $call);

        if (empty($call->recording_path)) {
            return response()->json([
                'success' => false,
                'error'   => 'Cannot retry transcription: no recording exists for this call.',
            ], 422);
        }

        $call->update([
            'transcript_status' => 'pending',
            'transcript_error'  => null,
        ]);

        TranscribeCallJob::dispatch($call->id, true);

        return response()->json([
            'success' => true,
            'message' => 'Transcription job queued.',
            'call'    => $this->formatCall($call->fresh()),
        ]);
    }

    /**
     * Helper to format Call object with temporary streaming URL.
     */
    protected function formatCall(Call $call): array
    {
        $audioUrl = null;
        if (!empty($call->recording_path)) {
            // Generate a 1-hour signed URL to stream private audio
            $audioUrl = URL::temporarySignedRoute(
                'api.calls.audio',
                now()->addHours(1),
                ['call' => $call->id]
            );
        }

        return [
            'id'                 => $call->id,
            'contact_id'         => $call->contact_id,
            'contact'            => $call->contact ? [
                'id'    => $call->contact->id,
                'name'  => $call->contact->name,
                'phone' => $call->contact->phone,
                'email' => $call->contact->email,
            ] : null,
            'user_id'            => $call->user_id,
            'agent_name'         => $call->user?->name ?: 'System Agent',
            'provider_call_sid'  => $call->provider_call_sid,
            'direction'          => $call->direction,
            'from_number'        => $call->from_number,
            'to_number'          => $call->to_number,
            'duration_seconds'   => $call->duration_seconds,
            'started_at'         => $call->started_at?->toIso8601String(),
            'ended_at'           => $call->ended_at?->toIso8601String(),
            'recording_path'     => $call->recording_path,
            'recording_url'      => $call->recording_url,
            'audio_url'          => $audioUrl,
            'has_recording'      => !empty($call->recording_path),
            'transcript'         => $call->transcript,
            'transcript_language'=> $call->transcript_language,
            'transcript_status'  => $call->transcript_status,
            'transcript_error'   => $call->transcript_error,
            'join_url'           => $call->join_token ? url("/call/join/{$call->join_token}") : null,
            'created_at'         => $call->created_at?->toIso8601String(),
        ];
    }

    /**
     * Authorize gate check.
     */
    protected function authorizeAccess(string $ability, mixed $arguments): void
    {
        $user = request()->user();
        if ($user && Gate::forUser($user)->denies($ability, $arguments)) {
            abort(403, 'Unauthorized access to call records or transcripts.');
        }
    }
}
