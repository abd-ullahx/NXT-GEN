<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(): JsonResponse
    {
        $contacts = Contact::orderBy('name', 'asc')->get()->map(fn($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'email'         => $c->email,
            'phone'         => $c->phone,
            'type'          => $c->type,
            'moves'         => $c->moves,
            'lifetimeValue' => (float) $c->lifetime_value,
            'status'        => $c->status,
        ])->toArray();

        // Include all registered surveyors & admins from users table
        $users = \App\Models\User::whereIn('role', ['surveyor', 'admin'])->get();
        foreach ($users as $u) {
            $exists = false;
            foreach ($contacts as $c) {
                if (strtolower($c['email']) === strtolower($u->email)) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $contacts[] = [
                    'id'            => ($u->role === 'admin' ? 'ADMIN-' : 'SURV-') . $u->id,
                    'name'          => $u->name,
                    'email'         => $u->email,
                    'phone'         => $u->role === 'admin' ? '+44 20 7946 0912' : '+44 7700 900555',
                    'type'          => ucfirst($u->role),
                    'moves'         => 0,
                    'lifetimeValue' => 0,
                    'status'        => 'Active',
                ];
            }
        }

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $newId = 'C-' . mt_rand(100000, 99999999);

        $contact = Contact::create([
            'id'             => $newId,
            'name'           => $request->input('name'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'type'           => $request->input('type', 'Customer'),
            'moves'          => $request->input('moves', 1),
            'lifetime_value' => $request->input('lifetimeValue', 1500),
            'status'         => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'id'            => $contact->id,
            'name'          => $contact->name,
            'email'         => $contact->email,
            'phone'         => $contact->phone,
            'type'          => $contact->type,
            'moves'         => $contact->moves,
            'lifetimeValue' => (float) $contact->lifetime_value,
            'status'        => $contact->status,
        ], 201);
    }
}
