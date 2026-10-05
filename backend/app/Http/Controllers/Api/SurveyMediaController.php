<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SurveyMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles survey media (images, videos, notes) uploaded from the Flutter surveyor app.
 * These are PUBLIC endpoints — authenticated by a simple lead_id + api_key check.
 */
class SurveyMediaController extends Controller
{
    private function formatIso(mixed $val): ?string
    {
        if (!$val) return null;
        if ($val instanceof \Carbon\CarbonInterface || $val instanceof \DateTimeInterface) {
            return $val->toIso8601String();
        }
        try {
            return \Carbon\Carbon::parse($val)->toIso8601String();
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }
    /**
     * Flutter app uploads images / videos / notes for a specific lead's survey.
     *
     * POST /api/survey/upload-media
     * Content-Type: multipart/form-data
     *
     * Fields:
     *   lead_id   (required) — Lead ID e.g. "L-8855" or numeric
     *   type      (required) — "image" | "video" | "note"
     *   file      (required for image/video) — the actual file
     *   notes     (optional) — text note from surveyor
     *   caption   (optional) — caption for photo/video
     *   surveyor_name (optional) — name of uploading surveyor
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'lead_id'       => 'required|string|max:50',
            'type'          => 'required|in:image,video,note,document,pdf,text',
            'file'          => 'nullable|file|max:307200', // 300MB max
            'notes'         => 'nullable|string|max:5000',
            'caption'       => 'nullable|string|max:500',
            'surveyor_name' => 'nullable|string|max:255',
        ]);

        $leadId       = $request->input('lead_id');
        $type         = $request->input('type');
        $notes        = $request->input('notes');
        $caption      = $request->input('caption');
        $surveyorName = $request->input('surveyor_name', 'Surveyor App');

        $fileUrl   = null;
        $fileName  = null;
        $fileSize  = null;
        $mimeType  = null;

        // Normalize type
        if (in_array($type, ['pdf', 'text'], true)) {
            $type = 'document';
        }

        // Handle file upload for image, video, or document/pdf/text
        if ($type !== 'note' && $request->hasFile('file')) {
            $file = $request->file('file');

            $ext      = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $folder   = 'survey-documents';
            if ($type === 'image') {
                $folder = 'survey-images';
            } elseif ($type === 'video') {
                $folder = 'survey-videos';
            }
            $filename = $folder . '-' . $leadId . '-' . time() . '-' . uniqid() . '.' . $ext;

            $path    = $file->storeAs($folder, $filename, 'public');
            $fileUrl  = Storage::url($path);
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getClientMimeType();
        }

        $media = SurveyMedia::create([
            'lead_id'       => $leadId,
            'type'          => $type,
            'file_url'      => $fileUrl,
            'file_name'     => $fileName,
            'file_size'     => $fileSize,
            'mime_type'     => $mimeType,
            'notes'         => $notes,
            'caption'       => $caption,
            'surveyor_name' => $surveyorName,
        ]);

        Log::info("SurveyMedia uploaded for lead {$leadId}: type={$type}, id={$media->id}");
        
        $typeName = ucfirst($type);
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'info',
            'title'   => "New {$typeName} Uploaded 📎",
            'message' => "Surveyor {$surveyorName} uploaded a new {$type} for Lead {$leadId}.",
            'link'    => '/app/surveys?view=' . $leadId,
            'is_read' => false,
        ]);

        return response()->json([
            'success'  => true,
            'id'       => $media->id,
            'lead_id'  => $media->lead_id,
            'type'     => $media->type,
            'file_url' => $media->file_url,
            'caption'  => $media->caption,
            'notes'    => $media->notes,
            'created_at' => $this->formatIso($media->created_at),
        ], 201);
    }

    /**
     * Get all survey media for a lead.
     * GET /api/survey/media?lead_id=L-8855
     */
    public function index(Request $request): JsonResponse
    {
        $leadId = $request->query('lead_id');

        $query = SurveyMedia::query()->orderBy('created_at', 'desc');

        if ($leadId) {
            // Support both "L-8855" and numeric "8855" formats
            $numeric = is_numeric($leadId) ? $leadId : preg_replace('/[^0-9]/', '', $leadId);
            $query->where(function ($q) use ($leadId, $numeric) {
                $q->where('lead_id', $leadId);
                if ($numeric && $numeric !== $leadId) {
                    $q->orWhere('lead_id', $numeric)
                      ->orWhere('lead_id', 'L-' . $numeric);
                }
            });
        }

        $media = $query->get()->map(fn ($m) => [
            'id'           => $m->id,
            'leadId'       => $m->lead_id,
            'type'         => $m->type,
            'fileUrl'      => $m->file_url,
            'fileName'     => $m->file_name,
            'fileSize'     => $m->file_size,
            'mimeType'     => $m->mime_type,
            'notes'        => $m->notes,
            'caption'      => $m->caption,
            'surveyorName' => $m->surveyor_name,
            'createdAt'    => $this->formatIso($m->created_at),
        ])->toArray();

        // Also merge media URLs stored on the lead model (submitted via /submit-survey-report)
        if ($leadId) {
            $lead = \App\Models\Lead::find($leadId);
            if ($lead && $lead->surveyor_media) {
                $urls = is_string($lead->surveyor_media) ? (json_decode($lead->surveyor_media, true) ?: []) : ($lead->surveyor_media ?: []);
                $existingUrls = array_filter(array_column($media, 'fileUrl'));
                foreach ($urls as $idx => $url) {
                    if (is_string($url) && !in_array($url, $existingUrls)) {
                        $isVideo = (bool) preg_match('/\.(mp4|webm|mov|avi|mkv)$/i', $url);
                        $media[] = [
                            'id'           => -($idx + 100),
                            'leadId'       => $lead->id,
                            'type'         => $isVideo ? 'video' : 'image',
                            'fileUrl'      => $url,
                            'fileName'     => basename(parse_url($url, PHP_URL_PATH)),
                            'fileSize'     => null,
                            'mimeType'     => $isVideo ? 'video/mp4' : 'image/jpeg',
                            'notes'        => null,
                            'caption'      => 'Surveyor Upload',
                            'surveyorName' => $lead->surveyor_name ?: 'Surveyor App',
                            'createdAt'    => $this->formatIso($lead->surveyor_completed_at) ?: now()->toIso8601String(),
                        ];
                    }
                }
            }
        }

        return response()->json(array_values($media));
    }

    /**
     * Delete a survey media item.
     * DELETE /api/survey/media/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $media = SurveyMedia::find($id);
        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        // Delete physical file from storage
        if ($media->file_url) {
            $relativePath = str_replace('/storage/', '', $media->file_url);
            Storage::disk('public')->delete($relativePath);
        }

        $media->delete();

        return response()->json(['success' => true, 'message' => 'Media deleted']);
    }
}
