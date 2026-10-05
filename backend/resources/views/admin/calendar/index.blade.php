@extends('layouts.admin')

@section('title', 'Calendar')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Scheduling
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Operations Calendar</h1>
            <p class="text-sm text-muted-foreground">Schedule & reschedule jobs, drivers, fleet, surveys and calls.</p>
        </div>
        <button onclick="document.getElementById('addEventForm').classList.toggle('hidden')" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Schedule Job
        </button>
    </div>

    <!-- Hidden form drawer/toggle -->
    <div id="addEventForm" class="hidden glass-card p-5 rounded-xl transition-all">
        <h2 class="font-display text-lg font-semibold mb-4 text-gold">New Calendar Event</h2>
        <form action="{{ route('admin.calendar.store') }}" method="POST">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Event Title *</label>
                    <input name="title" required placeholder="Eleanor - 4-Bed Move" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Event Type</label>
                    <select name="type" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                        <option value="Job">Job</option>
                        <option value="Survey">Survey</option>
                        <option value="Call">Call</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Date</label>
                    <input type="date" name="event_date" value="{{ now()->format('Y-m-d') }}" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Time</label>
                    <input name="event_time" value="08:00" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Driver / Team</label>
                    <input name="driver" value="Tomasz K." class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Vehicle</label>
                    <input name="vehicle" value="Luton 7.5t · NG21" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5 md:col-span-2">
                    <label class="text-xs text-muted-foreground">Location</label>
                    <input name="location" value="Slough → Windsor" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-sm font-semibold text-primary-foreground">Save Event</button>
                <button type="button" onclick="document.getElementById('addEventForm').classList.add('hidden')" class="rounded-xl border border-border bg-card/60 px-4 py-2 text-sm text-muted-foreground">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Calendar Month Layout -->
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="glass-card p-5 lg:col-span-2 rounded-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-xl font-semibold">June 2026</h2>
                <div class="flex items-center gap-1">
                    <button class="rounded-lg border border-border bg-card/40 p-1.5 hover:border-gold/40">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="rounded-lg border border-border bg-card/40 p-1.5 hover:border-gold/40">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-[11px] uppercase tracking-wider text-muted-foreground">
                @foreach(["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"] as $d)
                    <div class="py-2">{{ $d }}</div>
                @endforeach
            </div>
            
            <div class="grid grid-cols-7 gap-1">
                @php
                    $eventTypeMeta = [
                        'Job' => ['dot' => 'bg-gold', 'chip' => 'bg-gold/15 text-gold border border-gold/30'],
                        'Survey' => ['dot' => 'bg-cyan-500', 'chip' => 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30'],
                        'Call' => ['dot' => 'bg-amber-500', 'chip' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30'],
                        'Maintenance' => ['dot' => 'bg-orange-500', 'chip' => 'bg-orange-500/15 text-orange-400 border border-orange-500/30']
                    ];

                    // Draw calendar grid (June 2026 starts on Mon Jun 1)
                    $totalDays = 30;
                @endphp
                
                @for($day = 1; $day <= $totalDays; $day++)
                    @php
                        $currentDate = "2026-06-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $dayEvents = $events->filter(fn($e) => $e->event_date?->format('Y-m-d') === $currentDate);
                        $isSel = ($day === 12);
                    @endphp
                    
                    <button class="flex min-h-[72px] flex-col rounded-xl border p-1.5 text-left transition-all {{ $isSel ? 'border-gold/50 bg-gold/10' : 'border-border/40 hover:border-gold/30' }}">
                        <span class="text-xs {{ $isSel ? 'font-semibold text-gold' : 'text-muted-foreground' }}">{{ $day }}</span>
                        <div class="mt-1 space-y-0.5 w-full">
                            @foreach($dayEvents->take(2) as $e)
                                <div class="flex items-center gap-1 rounded px-1 py-0.5 text-[9px] truncate {{ $eventTypeMeta[$e->type]['chip'] }}">
                                    <span class="h-1 w-1 rounded-full {{ $eventTypeMeta[$e->type]['dot'] }}" />
                                    <span class="truncate">{{ $e->event_time }} {{ $e->title }}</span>
                                </div>
                            @endforeach
                            @if($dayEvents->count() > 2)
                                <div class="text-[9px] text-muted-foreground">+{{ $dayEvents->count() - 2 }} more</div>
                            @endif
                        </div>
                    </button>
                @endfor
            </div>
        </div>

        <!-- Day Detail -->
        <div class="glass-card p-5 rounded-xl">
            <h2 class="font-display text-xl font-semibold">Friday, 12 Jun</h2>
            <p class="text-xs text-muted-foreground">
                @php
                    $selectedDate = "2026-06-12";
                    $selectedEvents = $events->filter(fn($e) => $e->event_date?->format('Y-m-d') === $selectedDate);
                @endphp
                {{ $selectedEvents->count() }} scheduled item(s)
            </p>
            <div class="mt-4 space-y-3">
                @forelse($selectedEvents as $e)
                    <div class="rounded-xl border border-border/60 bg-card/40 p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-[10px] {{ $eventTypeMeta[$e->type]['chip'] }}">
                                {{ $e->type }}
                            </span>
                            <span class="text-sm font-semibold text-gold">{{ $e->event_time }}</span>
                        </div>
                        <div class="mt-2 text-sm font-medium">{{ $e->title }}</div>
                        <div class="mt-1 space-y-0.5 text-xs text-muted-foreground">
                            <div>📍 {{ $e->location }}</div>
                            <div>👤 {{ $e->driver }}</div>
                            @if($e->vehicle !== '—')
                                <div>🚚 {{ $e->vehicle }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-border/60 p-6 text-center text-sm text-muted-foreground">
                        Nothing scheduled.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
