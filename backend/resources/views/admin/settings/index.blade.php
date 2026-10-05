@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Configuration
        </div>
        <h1 class="mt-1 font-display text-4xl font-semibold">Settings</h1>
        <p class="text-sm text-muted-foreground">Manage your company profile, team and platform apps.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Business Profile Form -->
        <div class="glass-card p-6 lg:col-span-2 rounded-xl">
            <div class="mb-5 flex items-center gap-2">
                <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="22" x2="9" y2="16"/><line x1="15" y1="22" x2="15" y2="16"/><line x1="9" y1="16" x2="15" y2="16"/><line x1="12" y1="2" x2="12" y2="6"/></svg>
                <h2 class="font-display text-xl font-semibold">Business Profile</h2>
            </div>
            
            <div class="mb-6 flex items-center gap-4">
                <div class="relative shrink-0 overflow-hidden rounded-xl border border-gold/30 bg-background/60 w-14 h-14 gold-ring flex items-center justify-center font-display text-gold font-bold text-xl">
                    NG
                </div>
                <div>
                    <div class="font-medium">{{ $settings->business_name }}</div>
                    <div class="text-xs text-muted-foreground">{{ $settings->trading_region }} · Premium Removals</div>
                </div>
            </div>

            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs text-muted-foreground">Friendly Business Name</label>
                        <input name="business_name" value="{{ $settings->business_name }}" class="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs text-muted-foreground">Trading Region</label>
                        <input name="trading_region" value="{{ $settings->trading_region }}" class="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs text-muted-foreground">Contact Email</label>
                        <input name="contact_email" value="{{ $settings->contact_email }}" class="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs text-muted-foreground">Phone</label>
                        <input name="phone" value="{{ $settings->phone }}" class="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                    </div>
                </div>
                <button type="submit" class="mt-5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]">
                    Save changes
                </button>
            </form>
        </div>

        <!-- Preferences Switchboard -->
        <div class="space-y-6">
            <div class="glass-card p-6 rounded-xl">
                <div class="mb-4 flex items-center gap-2">
                    <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <h2 class="font-display text-xl font-semibold">Preferences</h2>
                </div>
                @php
                    $preferences = [
                        ['label' => 'Lead alerts', 'on' => true],
                        ['label' => 'Job reminders', 'on' => true],
                        ['label' => 'Two-factor auth', 'on' => false]
                    ];
                @endphp
                @foreach($preferences as $p)
                    <div class="flex items-center justify-between border-b border-border/40 py-3 last:border-0">
                        <span class="flex items-center gap-2 text-sm text-foreground">
                            <svg class="h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            {{ $p['label'] }}
                        </span>
                        <span class="relative h-5 w-9 rounded-full transition-colors cursor-pointer {{ $p['on'] ? 'bg-gold' : 'bg-muted' }}">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-background transition-all {{ $p['on'] ? 'left-[18px]' : 'left-0.5' }}"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Team Members List -->
    <div class="glass-card p-6 rounded-xl">
        <div class="mb-4 flex items-center gap-2">
            <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <h2 class="font-display text-xl font-semibold">Team Members</h2>
        </div>
        <div class="divide-y divide-border/40">
            @php
                $team = [
                    ['name' => 'Zul', 'email' => 'zul@nextgenrelocation.co.uk', 'role' => 'Admin'],
                    ['name' => 'Sales Desk', 'email' => 'sales@nextgenrelocation.co.uk', 'role' => 'Staff'],
                    ['name' => 'Tomasz Kowalski', 'email' => 'tomasz.k@nextgenrelocation.co.uk', 'role' => 'Driver']
                ];
            @endphp
            @foreach($team as $m)
                <div class="flex items-center gap-4 py-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-xs font-semibold text-gold">
                        @php
                            $words = explode(' ', $m['name']);
                            $initials = '';
                            foreach($words as $w) { $initials .= $w[0] ?? ''; }
                        @endphp
                        {{ strtoupper(substr($initials, 0, 2)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-foreground">{{ $m['name'] }}</div>
                        <div class="text-xs text-muted-foreground">{{ $m['email'] }}</div>
                    </div>
                    <span class="rounded-md border border-border bg-card/40 px-2 py-0.5 text-xs text-muted-foreground">{{ $m['role'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Platform Apps -->
    <div class="glass-card p-6 rounded-xl">
        <h2 class="mb-1 font-display text-xl font-semibold">Platform Apps</h2>
        <p class="mb-4 text-sm text-muted-foreground">One ecosystem — admin, customer, driver and desktop.</p>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $apps = [
                    ['title' => 'Web CRM', 'desc' => 'Full admin & staff workspace (this app).', 'icon' => 'monitor'],
                    ['title' => 'Customer App', 'desc' => 'Track jobs, sign documents, pay deposits.', 'icon' => 'phone'],
                    ['title' => 'Driver App', 'desc' => 'Daily jobs, routes & live GPS tracking.', 'icon' => 'phone'],
                    ['title' => 'Mac & Windows', 'desc' => 'Native desktop apps for the office team.', 'icon' => 'apple']
                ];
            @endphp
            @foreach($apps as $a)
                <div class="rounded-xl border border-border/60 bg-card/40 p-4">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold">
                        @if($a['icon'] === 'monitor')
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        @elseif($a['icon'] === 'phone')
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        @else
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.94c1.88-2.33 3-5.1 3-8.14a7 7 0 1 0-14 0c0 3.04 1.12 5.81 3 8.14M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z"/></svg>
                        @endif
                    </div>
                    <div class="text-sm font-medium text-foreground">{{ $a['title'] }}</div>
                    <p class="mt-1 text-xs text-muted-foreground">{{ $a['desc'] }}</p>
                    <span class="mt-3 inline-block rounded-full border border-gold/30 px-2 py-0.5 text-[10px] text-gold">Coming next</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
