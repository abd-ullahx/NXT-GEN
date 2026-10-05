<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::first();

        if (!$settings) {
            return response()->json([
                'businessName'  => 'NEXT GEN RELOCATION LTD',
                'tradingRegion' => 'Slough & Home Counties',
                'contactEmail'  => 'hello@nextgenrelocation.co.uk',
                'phone'         => '+44 1753 555 200',
            ]);
        }

        return response()->json([
            'businessName'  => $settings->business_name,
            'tradingRegion' => $settings->trading_region,
            'contactEmail'  => $settings->contact_email,
            'phone'         => $settings->phone,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = Setting::first();

        if (!$settings) {
            $settings = new Setting();
        }

        $settings->fill([
            'business_name'  => $request->input('businessName', $settings->business_name),
            'trading_region' => $request->input('tradingRegion', $settings->trading_region),
            'contact_email'  => $request->input('contactEmail', $settings->contact_email),
            'phone'          => $request->input('phone', $settings->phone),
        ]);

        $settings->save();

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->input('new_password')),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }
}
