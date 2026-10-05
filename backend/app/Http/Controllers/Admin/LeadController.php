<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index()
    {
        $leads = Lead::orderBy('created_at', 'desc')->get();
        return view('admin.leads.index', compact('leads'));
    }

    public function create()
    {
        return view('admin.leads.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:100',
            'move_type' => 'required|string|max:255',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
        ]);

        Lead::create([
            'id'            => 'L-' . mt_rand(100000, 99999999),
            'name'          => $request->name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'source'        => $request->input('source', 'Website Form'),
            'status'        => $request->input('status', 'new'),
            'move_type'     => $request->move_type,
            'from_location' => $request->from_location,
            'to_location'   => $request->to_location,
            'move_date'     => $request->input('move_date', now()->addMonth()->format('Y-m-d')),
            'est_value'     => $request->input('est_value', 2500),
            'ai_score'      => $request->input('ai_score', 8),
            'priority'      => $request->input('priority', 'Hot'),
        ]);

        return redirect()->route('admin.leads.index')->with('success', 'Lead created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $lead = Lead::findOrFail($id);

        $lead->update($request->only(['status', 'priority', 'est_value']));

        return redirect()->route('admin.leads.index')->with('success', 'Lead updated successfully.');
    }

    public function destroy(string $id)
    {
        \App\Models\JobEvent::where('lead_id', $id)->delete();
        \App\Models\Quotation::where('lead_id', $id)->delete();
        \App\Models\Email::where('lead_id', $id)->delete();
        Lead::destroy($id);

        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted successfully.');
    }
}
