<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;

class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        $integrations = Integration::all()->map(fn($i) => [
            'id'        => $i->id,
            'name'      => $i->name,
            'desc'      => $i->desc_text,
            'category'  => $i->category,
            'connected' => (bool) $i->connected,
            'color'     => $i->color,
        ]);

        return response()->json($integrations);
    }

    public function toggle(string $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);
        $integration->connected = !$integration->connected;
        $integration->save();

        return response()->json(['message' => 'Integration status toggled', 'id' => $id]);
    }
}
