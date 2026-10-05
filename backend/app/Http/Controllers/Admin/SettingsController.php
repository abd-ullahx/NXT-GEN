<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::first() ?? new Setting([
            'business_name'  => 'NEXT GEN RELOCATION LTD',
            'trading_region' => 'Slough & Home Counties',
            'contact_email'  => 'hello@nextgenrelocation.co.uk',
            'phone'          => '+44 1753 555 200',
        ]);

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
        ]);

        $settings = Setting::first();

        if (!$settings) {
            $settings = new Setting();
        }

        $settings->fill($request->only(['business_name', 'trading_region', 'contact_email', 'phone']));
        $settings->save();

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved successfully.');
    }
}
