@extends('layouts.admin')

@section('title', 'Leads')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Pipeline
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">All Leads</h1>
            <p class="text-sm text-muted-foreground">Manage and track every lead across all sources.</p>
        </div>
        <div class="flex gap-2">
            <button class="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2.5 text-sm transition-colors hover:border-gold/40">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
            </button>
            <a href="{{ route('admin.leads.create') }}" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Lead
            </a>
        </div>
    </div>

    <!-- Source Chips -->
    <div class="flex flex-wrap items-center gap-2">
        @php
            $sources = ["All", "PinLocal", "CompareMyMove", "reallymoving", "Website Form", "Facebook", "Google Ads", "Referral", "Phone"];
            $activeSource = request('source', 'All');
        @endphp
        @foreach($sources as $s)
            <a href="{{ route('admin.leads.index', ['source' => $s]) }}" class="rounded-full border px-3.5 py-1.5 text-xs transition-all {{ $activeSource === $s ? 'border-gold/50 bg-gold/15 text-gold' : 'border-border bg-card/40 text-muted-foreground hover:border-gold/30 hover:text-foreground' }}">
                {{ $s }}
            </a>
        @endforeach
    </div>

    <div class="glass-card overflow-hidden rounded-xl">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 border-b border-border/60 p-4">
            <div class="relative min-w-[200px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <form method="GET" action="{{ route('admin.leads.index') }}">
                    @if(request('source'))
                        <input type="hidden" name="source" value="{{ request('source') }}">
                    @endif
                    <input
                        name="query"
                        value="{{ request('query') }}"
                        placeholder="Search leads…"
                        class="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
                        onchange="this.form.submit()"
                    />
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead>
                    <tr class="border-b border-border/60 text-xs uppercase tracking-wider text-muted-foreground">
                        <th class="px-5 py-3 font-medium">Customer</th>
                        <th class="px-5 py-3 font-medium">Move</th>
                        <th class="px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3 font-medium">Value</th>
                        <th class="px-5 py-3 font-medium">AI Score</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $statusTones = [
                            'new' => 'text-cyan-400',
                            'contacted' => 'text-amber-400',
                            'survey-booked' => 'text-gold',
                            'quoted' => 'text-purple-400',
                            'won' => 'text-success',
                            'lost' => 'text-destructive'
                        ];

                        $filteredLeads = $leads;
                        if ($activeSource !== 'All') {
                            $filteredLeads = $filteredLeads->where('source', $activeSource);
                        }
                        if (request('query')) {
                            $q = strtolower(request('query'));
                            $filteredLeads = $filteredLeads->filter(fn($l) => str_contains(strtolower($l->name), $q) || str_contains(strtolower($l->email), $q) || str_contains(strtolower($l->move_type), $q));
                        }
                    @endphp

                    @forelse($filteredLeads as $l)
                        <tr class="border-b border-border/40 transition-colors hover:bg-gold/[0.04]">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-xs font-semibold text-gold">
                                        @php
                                            $words = explode(' ', $l->name);
                                            $initials = '';
                                            foreach($words as $w) { $initials .= $w[0] ?? ''; }
                                        @endphp
                                        {{ strtoupper(substr($initials, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $l->name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $l->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-sm">{{ $l->move_type }}</div>
                                <div class="text-xs text-muted-foreground">{{ $l->from_location }} → {{ $l->to_location }}</div>
                            </td>
                            <td class="px-5 py-4 text-muted-foreground">{{ $l->source }}</td>
                            <td class="px-5 py-4 font-medium">£{{ number_format($l->est_value) }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs {{ $l->ai_score >= 8 ? 'border-gold/40 bg-gold/15 text-gold' : ($l->ai_score >= 6 ? 'border-warning/40 bg-warning/15 text-warning' : 'border-border bg-muted/40 text-muted-foreground') }}">
                                    @if($l->ai_score >= 8)
                                        <svg class="h-3 w-3 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                                    @endif
                                    {{ $l->ai_score }}/10
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-xs font-medium {{ $statusTones[$l->status] ?? 'text-muted-foreground' }}">{{ ucwords(str_replace('-', ' ', $l->status)) }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form action="{{ route('admin.leads.destroy', $l->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this lead?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-muted-foreground hover:text-destructive transition-colors">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colSpan="7" class="px-5 py-12 text-center text-sm text-muted-foreground">
                                No leads match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
