<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobEvent;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index()
    {
        $events = JobEvent::orderBy('event_date', 'asc')->get();
        return view('admin.calendar.index', compact('events'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        JobEvent::create([
            'id'         => 'J-' . mt_rand(100000, 99999999),
            'title'      => $request->title,
            'type'       => $request->input('type', 'Job'),
            'event_date' => $request->input('event_date', now()->addWeek()->format('Y-m-d')),
            'event_time' => $request->input('event_time', '09:00'),
            'driver'     => $request->input('driver', 'Tomasz K.'),
            'vehicle'    => $request->input('vehicle', '3.5t Van'),
            'location'   => $request->input('location', 'Slough'),
        ]);

        return redirect()->route('admin.calendar.index')->with('success', 'Event created successfully.');
    }
}
