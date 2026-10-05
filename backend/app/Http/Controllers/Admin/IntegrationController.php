<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integration;

class IntegrationController extends Controller
{
    public function index()
    {
        $integrations = Integration::all();
        return view('admin.integrations.index', compact('integrations'));
    }

    public function toggle(string $id)
    {
        $integration = Integration::findOrFail($id);
        $integration->connected = !$integration->connected;
        $integration->save();

        return redirect()->route('admin.integrations.index')->with('success', 'Integration toggled.');
    }
}
