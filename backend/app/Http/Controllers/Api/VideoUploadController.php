<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SurveyVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VideoUploadController extends Controller
{
    /**
     * Get list of survey video recordings.
     */
    public function index(Request $request): JsonResponse
    {
        $leadId = $request->query('lead_id');

        $videos = SurveyVideo::with('uploader:id,name,role')
            ->when($leadId, function ($q) use ($leadId) {
                $q->where('lead_id', $leadId);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($v) {
                return [
                    'id'              => $v->id,
                    'leadId'          => $v->lead_id,
                    'userId'          => $v->user_id,
                    'title'           => $v->title,
                    'fileName'        => $v->file_name,
                    'videoUrl'        => $v->video_url,
                    'fileSize'        => $v->file_size,
                    'mimeType'        => $v->mime_type,
                    'durationSeconds' => $v->duration_seconds,
                    'notes'           => $v->notes,
                    'createdAt'       => $v->created_at ? \Carbon\Carbon::parse($v->created_at)->toIso8601String() : null,
                    'uploaderName'    => $v->uploader?->name ?: 'Staff',
                ];
            });

        return response()->json($videos);
    }

    /**
     * Upload a video recording file (.mp4, .webm, .mov).
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'video'   => 'required|file|mimes:mp4,webm,mov,avi,mkv|max:204800', // 200MB max
            'title'   => 'nullable|string|max:255',
            'lead_id' => 'nullable|string|max:50',
            'notes'   => 'nullable|string',
        ]);

        $file = $request->file('video');
        $originalName = $file->getClientOriginalName();
        $filename = 'survey-video-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();

        // Store file in public disk under storage/app/public/videos
        $path = $file->storeAs('videos', $filename, 'public');
        $videoUrl = Storage::url($path);

        $video = SurveyVideo::create([
            'lead_id'          => $request->input('lead_id'),
            'user_id'          => Auth::id(),
            'title'            => $request->input('title') ?: $originalName,
            'file_name'        => $originalName,
            'video_url'        => $videoUrl,
            'file_size'        => $file->getSize(),
            'mime_type'        => $file->getClientMimeType() ?: 'video/mp4',
            'duration_seconds' => $request->input('duration_seconds', 0),
            'notes'            => $request->input('notes'),
            'created_at'       => now(),
        ]);

        return response()->json([
            'id'              => $video->id,
            'leadId'          => $video->lead_id,
            'title'           => $video->title,
            'fileName'        => $video->file_name,
            'videoUrl'        => $video->video_url,
            'fileSize'        => $video->file_size,
            'mimeType'        => $video->mime_type,
            'durationSeconds' => $video->duration_seconds,
            'notes'           => $video->notes,
            'createdAt'       => $video->created_at ? \Carbon\Carbon::parse($video->created_at)->toIso8601String() : null,
        ], 201);
    }

    /**
     * Delete a survey video recording.
     */
    public function destroy(int $id): JsonResponse
    {
        $video = SurveyVideo::findOrFail($id);
        $video->delete();

        return response()->json(['message' => 'Video recording deleted']);
    }
}
