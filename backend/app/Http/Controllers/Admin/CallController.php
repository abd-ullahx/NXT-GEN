<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranscribeCallJob;
use App\Models\Call;
use App\Models\Contact;
use App\Services\CallRecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CallController extends Controller
{
    public function index(Request $request)
    {
        $query = Call::with(['contact', 'user'])->latest('started_at');

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->filled('status')) {
            $query->where('transcript_status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $query->searchTranscript($request->input('q'));
        }

        $calls = $query->paginate(20)->withQueryString();
        $contacts = Contact::orderBy('name')->get();

        return view('admin.calls.index', compact('calls', 'contacts'));
    }

    public function show(Call $call)
    {
        $call->load(['contact', 'user']);
        return view('admin.calls.show', compact('call'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'contact_id'  => 'nullable|string|exists:contacts,id',
            'from_number' => 'nullable|string|max:50',
            'to_number'   => 'nullable|string|max:50',
            'direction'   => 'nullable|in:inbound,outbound',
        ]);

        $joinToken = Str::random(40);
        $providerCallSid = 'call_' . Str::random(24);

        $call = Call::create([
            'contact_id'            => $request->input('contact_id'),
            'user_id'               => auth()->id(),
            'provider_call_sid'     => $providerCallSid,
            'direction'             => $request->input('direction', 'outbound'),
            'from_number'           => $request->input('from_number') ?: (auth()->user()?->name ?: 'Office'),
            'to_number'             => $request->input('to_number'),
            'started_at'            => now(),
            'transcript_status'     => 'pending',
            'join_token'            => $joinToken,
            'join_token_expires_at' => now()->addHours(2),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'call'    => $call,
                'joinUrl' => url("/call/join/{$joinToken}"),
                'roomCode'=> $providerCallSid,
            ]);
        }

        return redirect()->route('admin.calls.show', $call)
            ->with('success', 'Call initiated. Share the invitation link with the contact.');
    }

    public function upload(Request $request, CallRecordingService $recordingService)
    {
        $maxKb = (int) config('services.whisper.max_upload_size_kb', 102400);

        $request->validate([
            'recording'        => "required|file|max:{$maxKb}",
            'contact_id'       => 'nullable|string|exists:contacts,id',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $contactId = $request->input('contact_id');
        $contact = $contactId ? Contact::find($contactId) : null;

        $call = Call::create([
            'contact_id'        => $contactId,
            'user_id'           => auth()->id(),
            'provider_call_sid' => 'upload_' . Str::random(24),
            'direction'         => 'outbound',
            'from_number'       => auth()->user()?->name ?: 'Office',
            'to_number'         => $contact?->phone ?: 'Client',
            'started_at'        => now(),
            'ended_at'          => now(),
            'transcript_status' => 'pending',
        ]);

        $recordingService->storeAndDispatch(
            $call,
            $request->file('recording'),
            null,
            $request->input('duration_seconds') ? (int) $request->input('duration_seconds') : null
        );

        return redirect()->route('admin.calls.show', $call)
            ->with('success', 'Recording uploaded successfully! Whisper transcription is now processing.');
    }

    public function retry(Call $call)
    {
        if (empty($call->recording_path)) {
            return back()->with('error', 'Cannot transcribe: this call does not have an audio recording file.');
        }

        $call->update([
            'transcript_status' => 'pending',
            'transcript_error'  => null,
        ]);

        TranscribeCallJob::dispatch($call->id, true);

        return back()->with('success', 'Transcription job dispatched to the queue.');
    }
}
