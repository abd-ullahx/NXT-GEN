<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CallJoinController extends Controller
{
    /**
     * Show the public browser calling join screen for the contact.
     */
    public function show(string $token): View
    {
        $call = Call::where('join_token', $token)->first();

        if (!$call) {
            abort(404, 'Call session not found or link has expired.');
        }

        if ($call->join_token_expires_at && $call->join_token_expires_at->isPast()) {
            abort(410, 'This call invitation link has expired.');
        }

        return view('calls.join', [
            'call'     => $call,
            'contact'  => $call->contact,
            'roomCode' => $call->provider_call_sid,
        ]);
    }
}
