@extends('layouts.admin')

@section('title', 'Call Details — #' . $call->id)

@section('content')
<div class="space-y-6 max-w-5xl">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                <a href="{{ route('admin.calls.index') }}" class="hover:text-gold flex items-center gap-1">
                    ← Back to Calls
                </a>
                <span>/</span>
                <span>Call #{{ $call->id }}</span>
            </div>
            <h1 class="font-display text-3xl font-semibold text-foreground">
                Call with {{ $call->contact?->name ?: ($call->to_number ?: 'Contact') }}
            </h1>
            <p class="text-xs text-muted-foreground mt-0.5">
                {{ $call->started_at?->format('d M Y, H:i') ?: 'Recent Call' }} · Provider SID: <code class="font-mono text-[11px] text-gold">{{ $call->provider_call_sid }}</code>
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($call->transcript_status === 'failed')
                <form method="POST" action="{{ route('admin.calls.retry', $call) }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 rounded-xl bg-gold/20 border border-gold/40 px-4 py-2 text-xs font-semibold text-gold hover:bg-gold/30">
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        Retry Transcription
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-border bg-card/40 p-4">
            <span class="text-xs uppercase tracking-wider text-muted-foreground">Direction</span>
            <div class="mt-1 font-semibold text-foreground flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 rounded-full {{ $call->direction === 'inbound' ? 'bg-blue-400' : 'bg-emerald-400' }}"></span>
                {{ ucfirst($call->direction) }}
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-card/40 p-4">
            <span class="text-xs uppercase tracking-wider text-muted-foreground">Duration</span>
            <div class="mt-1 font-mono text-lg font-bold text-gold">
                {{ $call->duration_seconds ? gmdate('i:s', $call->duration_seconds) : '—' }}
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-card/40 p-4">
            <span class="text-xs uppercase tracking-wider text-muted-foreground">Language</span>
            <div class="mt-1 font-semibold text-foreground uppercase">
                {{ $call->transcript_language ?: 'EN' }}
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-card/40 p-4">
            <span class="text-xs uppercase tracking-wider text-muted-foreground">Transcript Status</span>
            <div class="mt-1">
                @if($call->transcript_status === 'done')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-500/30">
                        ● Completed
                    </span>
                @elseif($call->transcript_status === 'processing')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/15 px-2.5 py-0.5 text-xs font-semibold text-blue-400 border border-blue-500/30">
                        <span class="h-1.5 w-1.5 animate-ping rounded-full bg-blue-400"></span> Transcribing...
                    </span>
                @elseif($call->transcript_status === 'failed')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-500/15 px-2.5 py-0.5 text-xs font-semibold text-red-400 border border-red-500/30">
                        Failed
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gold/15 px-2.5 py-0.5 text-xs font-semibold text-gold border border-gold/30">
                        Pending
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Audio Player Card -->
    @if(!empty($call->recording_path))
        @php
            $signedAudioUrl = URL::temporarySignedRoute('api.calls.audio', now()->addHours(1), ['call' => $call->id]);
        @endphp
        <div class="rounded-2xl border border-border bg-card/40 p-6 backdrop-blur-md">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-display text-lg font-semibold text-foreground flex items-center gap-2">
                    <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    Call Audio Recording
                </h3>
                <span class="text-xs text-muted-foreground font-mono">{{ basename($call->recording_path) }}</span>
            </div>
            <audio controls class="w-full h-11 rounded-xl" src="{{ $signedAudioUrl }}"></audio>
        </div>
    @endif

    <!-- Transcript Card -->
    <div class="rounded-2xl border border-border bg-card/40 p-6 backdrop-blur-md">
        <div class="flex items-center justify-between pb-4 border-b border-border/80">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <h3 class="font-display text-lg font-semibold text-foreground">AI Transcription (Whisper)</h3>
            </div>
            @if(!empty($call->transcript))
                <button onclick="copyTranscript()" class="rounded-lg border border-border bg-card/60 px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:text-gold hover:border-gold/40 transition-colors">
                    Copy Text
                </button>
            @endif
        </div>

        <div class="mt-6">
            @if($call->transcript_status === 'done' && !empty($call->transcript))
                <div class="rounded-xl border border-gold/15 bg-background/40 p-5 text-sm leading-relaxed text-foreground/90 font-serif whitespace-pre-wrap selection:bg-gold/30 selection:text-foreground" id="transcript-body">
{{ $call->transcript }}
                </div>
            @elseif($call->transcript_status === 'processing')
                <div class="py-12 text-center text-muted-foreground">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-gold border-t-transparent mb-3"></div>
                    <p class="text-sm font-semibold text-foreground">Whisper microservice is transcribing this call...</p>
                    <p class="text-xs text-muted-foreground mt-1">This page will automatically refresh in a few seconds.</p>
                </div>
                <script>
                    setTimeout(() => window.location.reload(), 5000);
                </script>
            @elseif($call->transcript_status === 'failed')
                <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-5">
                    <h4 class="text-sm font-semibold text-red-400">Transcription Failed</h4>
                    <p class="text-xs text-red-300 mt-1 font-mono">{{ $call->transcript_error ?: 'An unknown error occurred during Whisper processing.' }}</p>
                    <div class="mt-4">
                        <form method="POST" action="{{ route('admin.calls.retry', $call) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-red-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                Retry Transcription
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="py-8 text-center text-muted-foreground text-sm">
                    Transcription pending in queue. Ensure the queue worker (<code>php artisan queue:work</code>) and Whisper service are active.
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function copyTranscript() {
        const text = document.getElementById('transcript-body').innerText;
        navigator.clipboard.writeText(text);
        alert('Transcript copied to clipboard!');
    }
</script>
@endsection
