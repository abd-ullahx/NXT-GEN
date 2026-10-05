@extends('layouts.admin')

@section('title', 'Contacts')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Directory
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Contacts</h1>
            <p class="text-sm text-muted-foreground">Customers, drivers and suppliers in one place.</p>
        </div>
        <a href="{{ route('admin.contacts.create') }}" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Contact
        </a>
    </div>

    <!-- Tabs and search -->
    <div class="flex flex-wrap items-center gap-2 justify-between">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $tabs = ["All", "Customer", "Surveyor", "Driver", "Supplier"];
                $activeTab = request('tab', 'All');
            @endphp
            @foreach($tabs as $t)
                <a href="{{ route('admin.contacts.index', ['tab' => $t]) }}" class="rounded-full border px-3.5 py-1.5 text-xs transition-all {{ $activeTab === $t ? 'border-gold/50 bg-gold/15 text-gold font-semibold' : 'border-border bg-card/40 text-muted-foreground hover:border-gold/30' }}">
                    {{ $t }}
                </a>
            @endforeach
        </div>
        <div class="relative min-w-[200px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <form method="GET" action="{{ route('admin.contacts.index') }}">
                @if(request('tab'))
                    <input type="hidden" name="tab" value="{{ request('tab') }}">
                @endif
                <input
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search contacts & surveyors…"
                    class="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
                    onchange="this.form.submit()"
                />
            </form>
        </div>
    </div>

    <!-- Grid -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @php
            $filteredContacts = $contacts;
            if ($activeTab !== 'All') {
                $filteredContacts = $filteredContacts->where('type', $activeTab);
            }
            if (request('q')) {
                $search = strtolower(request('q'));
                $filteredContacts = $filteredContacts->filter(fn($c) => str_contains(strtolower($c->name), $search));
            }
        @endphp

        @forelse($filteredContacts as $c)
            <div class="glass-card hover p-5 rounded-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-sm font-semibold text-gold">
                                @php
                                    $words = explode(' ', $c->name);
                                    $initials = '';
                                    foreach($words as $w) { $initials .= $w[0] ?? ''; }
                                @endphp
                                {{ strtoupper(substr($initials, 0, 2)) }}
                            </div>
                            <div>
                                <div class="font-medium flex items-center gap-1.5">
                                    {{ $c->name }}
                                    @if($c->type === 'Surveyor')
                                        <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-400">SURVEYOR</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                    @if($c->type === 'Customer')
                                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    @elseif($c->type === 'Surveyor')
                                        <svg class="h-3 w-3 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    @elseif($c->type === 'Driver')
                                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                    @else
                                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="22" x2="9" y2="16"/><line x1="15" y1="22" x2="15" y2="16"/><line x1="9" y1="16" x2="15" y2="16"/><path d="M12 2v4"/></svg>
                                    @endif
                                    {{ $c->type }}
                                </div>
                            </div>
                        </div>
                        <span class="rounded-md px-2 py-0.5 text-[10px] {{ $c->status === 'Active' ? 'bg-success/15 text-success' : 'bg-muted/40 text-muted-foreground' }}">
                            {{ $c->status }}
                        </span>
                    </div>
                    <div class="mt-4 space-y-1.5 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            {{ $c->email }}
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 9.09v3z"/></svg>
                            {{ $c->phone }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-border/50 pt-3 text-xs">
                    @if($c->type === 'Customer')
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">{{ $c->moves }} move(s)</span>
                            <span class="font-semibold text-gold">£{{ number_format($c->lifetime_value) }} LTV</span>
                        </div>
                        <a href="{{ route('admin.calls.index', ['contact_id' => $c->id]) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-gold hover:underline">
                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            Calls & Transcripts
                        </a>
                    @elseif($c->type === 'Surveyor')
                        <span class="text-amber-400 font-semibold text-[11px]">Field Inspector</span>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.calls.index', ['contact_id' => $c->id]) }}" class="text-[11px] text-muted-foreground hover:text-gold">Calls</a>
                            @php $roomCode = 'Surveyor-' . preg_replace('/[^a-zA-Z0-9]/', '-', $c->name) . '-' . $c->id; @endphp
                            <a href="https://meet.jit.si/{{ $roomCode }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-500/40 bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-400 hover:bg-amber-500/25 transition-all">
                                Video Call
                            </a>
                        </div>
                    @else
                        <span class="text-muted-foreground">Verified Directory Contact</span>
                        <a href="{{ route('admin.calls.index', ['contact_id' => $c->id]) }}" class="text-[11px] font-semibold text-gold hover:underline">
                            Call Logs
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-sm text-muted-foreground">
                No contacts found.
            </div>
        @endforelse
    </div>
</div>
@endsection
