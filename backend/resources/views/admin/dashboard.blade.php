@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Command Centre
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Good morning, Zul</h1>
            <p class="text-sm text-muted-foreground">Here's how Next Gen Relocation is performing today.</p>
        </div>
        <a href="{{ route('admin.leads.create') }}" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Lead
        </a>
    </div>

    <!-- KPIs -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $kpis = [
                ['label' => 'Pipeline Value', 'value' => '£186,400', 'delta' => '+12.4%'],
                ['label' => 'Active Leads', 'value' => $leadsCount, 'delta' => '+8 this week'],
                ['label' => 'Jobs This Month', 'value' => $jobsCount, 'delta' => '+3 vs last'],
                ['label' => 'Won Revenue (30d)', 'value' => '£64,300', 'delta' => '+9.2%']
            ];
        @endphp

        @foreach($kpis as $k)
            <div class="glass-card hover p-5 rounded-xl">
                <div class="text-xs uppercase tracking-wider text-muted-foreground">{{ $k['label'] }}</div>
                <div class="mt-2 font-display text-3xl font-semibold">{{ $k['value'] }}</div>
                <div class="mt-2 flex items-center gap-1 text-xs text-success">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                    {{ $k['delta'] }}
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Monthly Performance bar chart placeholder -->
        <div class="glass-card p-6 lg:col-span-2 rounded-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-semibold">Revenue vs Expenses</h2>
                    <p class="text-xs text-muted-foreground">Last 6 months</p>
                </div>
                <div class="flex items-center gap-2 rounded-full border border-success/30 bg-success/10 px-3 py-1 text-xs text-success">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    +18.6% YoY
                </div>
            </div>
            
            <div class="h-64 flex items-end gap-6 pt-6">
                @php $maxVal = collect($revenueByMonth)->max('revenue'); @endphp
                @foreach($revenueByMonth as $month)
                    <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                        <div class="flex gap-2 items-end w-full justify-center h-48">
                            <div class="w-4 rounded-t bg-gold" style="height: {{ ($month['revenue'] / $maxVal) * 100 }}%;" title="£{{ number_format($month['revenue']) }}"></div>
                            <div class="w-4 rounded-t bg-muted" style="height: {{ ($month['expenses'] / $maxVal) * 100 }}%;" title="£{{ number_format($month['expenses']) }}"></div>
                        </div>
                        <div class="text-xs text-muted-foreground">{{ $month['month'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="flex items-center gap-4 justify-center mt-4">
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="h-3 w-3 rounded-sm bg-gold"></span> Revenue
                </div>
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="h-3 w-3 rounded-sm bg-muted"></span> Expenses
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="glass-card p-6 rounded-xl">
            <div class="mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <h2 class="font-display text-xl font-semibold">Upcoming</h2>
            </div>
            <div class="space-y-3">
                @php
                    $typeMeta = [
                        'Job' => ['dot' => 'bg-gold', 'chip' => 'bg-gold/15 text-gold border border-gold/30'],
                        'Survey' => ['dot' => 'bg-cyan-500', 'chip' => 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30'],
                        'Call' => ['dot' => 'bg-amber-500', 'chip' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30'],
                        'Maintenance' => ['dot' => 'bg-orange-500', 'chip' => 'bg-orange-500/15 text-orange-400 border border-orange-500/30']
                    ];
                @endphp
                @foreach($upcomingEvents as $e)
                    <div class="flex items-start gap-3 rounded-xl border border-border/60 bg-card/40 p-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $typeMeta[$e->type]['dot'] }}" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ $e->title }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ $e->event_date?->format('Y-m-d') }} · {{ $e->event_time }} · {{ $e->driver }}
                            </div>
                        </div>
                        <span class="rounded-md px-2 py-0.5 text-[10px] {{ $typeMeta[$e->type]['chip'] }}">{{ $e->type }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Recent leads -->
    <div class="glass-card p-6 rounded-xl">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold">Latest Leads</h2>
            <svg class="h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 9.09v3z"/></svg>
        </div>
        <div class="space-y-2">
            @php
                $statusTones = [
                    'new' => 'text-cyan-400',
                    'contacted' => 'text-amber-400',
                    'survey-booked' => 'text-gold',
                    'quoted' => 'text-purple-400',
                    'won' => 'text-success',
                    'lost' => 'text-destructive'
                ];
            @endphp
            @foreach($recentLeads as $l)
                <div class="flex items-center gap-4 rounded-xl border border-border/50 bg-card/30 px-4 py-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-sm font-semibold text-gold">
                        @php
                            $words = explode(' ', $l->name);
                            $initials = '';
                            foreach($words as $w) { $initials .= $w[0] ?? ''; }
                        @endphp
                        {{ strtoupper(substr($initials, 0, 2)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $l->name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ $l->move_type }}</div>
                    </div>
                    <div class="hidden text-xs text-muted-foreground sm:block">{{ $l->source }}</div>
                    <div class="hidden font-medium text-foreground md:block">£{{ number_format($l->est_value) }}</div>
                    <span class="text-xs font-medium {{ $statusTones[$l->status] ?? 'text-muted-foreground' }}">{{ ucwords(str_replace('-', ' ', $l->status)) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
