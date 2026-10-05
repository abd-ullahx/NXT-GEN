<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::orderBy('name', 'asc')->get();
        $surveyors = \App\Models\User::where('role', 'surveyor')->get();
        foreach ($surveyors as $s) {
            if (!$contacts->firstWhere('email', $s->email)) {
                $contacts->push(new Contact([
                    'id'             => 'SURV-' . $s->id,
                    'name'           => $s->name,
                    'email'          => $s->email,
                    'phone'          => '+44 7700 900555',
                    'type'           => 'Surveyor',
                    'moves'          => 0,
                    'lifetime_value' => 0,
                    'status'         => 'Active',
                ]));
            }
        }
        return view('admin.contacts.index', compact('contacts'));
    }

    public function create()
    {
        return view('admin.contacts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:100',
        ]);

        Contact::create([
            'id'             => 'C-' . mt_rand(100000, 99999999),
            'name'           => $request->name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'type'           => $request->input('type', 'Customer'),
            'moves'          => $request->input('moves', 0),
            'lifetime_value' => $request->input('lifetime_value', 0),
            'status'         => $request->input('status', 'Active'),
        ]);

        return redirect()->route('admin.contacts.index')->with('success', 'Contact created successfully.');
    }
}
