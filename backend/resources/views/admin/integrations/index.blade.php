@extends('layouts.admin')

@section('title', 'Integrations')

@section('content')
<div class="space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Connections
        </div>
        <h1 class="mt-1 font-display text-4xl font-semibold">Integrations</h1>
        <p class="text-sm text-muted-foreground">Connect lead sources, email, AI and automation tools.</p>
    </div>

    <!-- Highlights -->
    <div class="grid gap-4 md:grid-cols-3">
        @php
            $highlights = [
                ['title' => 'Outlook Auto-Sync', 'desc' => 'Inbound emails captured as leads; outbound replies tracked automatically.', 'icon' => 'mail'],
                ['title' => 'n8n Workflows', 'desc' => 'Route emails to AI, build draft replies, and trigger any automation.', 'icon' => 'workflow'],
                ['title' => 'Multi-AI Drafting', 'desc' => 'ChatGPT & Claude generate quotes, replies and summaries on demand.', 'icon' => 'bot']
            ];
        @endphp
        @foreach($highlights as $h)
            <div class="glass-card p-5 rounded-xl">
                <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold">
                    @if($h['icon'] === 'mail')
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    @elseif($h['icon'] === 'workflow')
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    @else
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/></svg>
                    @endif
                </div>
                <div class="font-display text-lg font-semibold">{{ $h['title'] }}</div>
                <p class="mt-1 text-sm text-muted-foreground">{{ $h['desc'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold">All Integrations</h2>
        <button class="flex items-center gap-2 rounded-xl border border-dashed border-gold/40 px-3.5 py-2 text-xs text-gold hover:bg-gold/10">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Request integration
        </button>
    </div>

    <!-- Integrations Grid -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($integrations as $i)
            <div class="glass-card hover flex flex-col p-5 rounded-xl">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold text-primary-foreground" style="background: {{ $i->color }}">
                            {{ strtoupper(substr($i->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="font-medium">{{ $i->name }}</div>
                            <div class="text-xs text-muted-foreground">{{ $i->category }}</div>
                        </div>
                    </div>
                    @if($i->connected)
                        <span class="flex items-center gap-1 rounded-full bg-success/15 px-2 py-0.5 text-[10px] text-success">
                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Connected
                        </span>
                    @endif
                </div>
                <p class="mt-3 flex-1 text-sm text-muted-foreground">{{ $i->desc_text }}</p>
                
                <form action="{{ route('admin.integrations.toggle', $i->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="w-full mt-4 rounded-xl py-2 text-sm font-medium transition-all {{ $i->connected ? 'border border-border text-muted-foreground hover:border-destructive/40 hover:text-destructive' : 'bg-gradient-to-r from-gold-bright to-gold-dim text-primary-foreground hover:shadow-[var(--shadow-gold)]' }}">
                        {{ $i->connected ? 'Disconnect' : 'Connect' }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <!-- Live Cloud notice -->
    <div class="glass-card flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center rounded-xl">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="flex-1">
            <div class="font-display text-lg font-semibold">Wire up the real backend</div>
            <p class="text-sm text-muted-foreground">
                Enable Lovable Cloud to power live Outlook sync, n8n webhooks, AI drafting and secure auth.
            </p>
        </div>
        <button class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2.5 text-sm font-semibold text-primary-foreground">
            Get started
        </button>
    </div>
</div>
@endsection
