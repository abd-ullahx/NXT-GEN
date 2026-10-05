@extends('layouts.admin')

@section('title', 'Outlook Inbox')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Outlook
                @if($status['connected'])
                    <span class="ml-2 flex items-center gap-1 rounded-full bg-success/15 px-2 py-0.5 text-[10px] text-success border border-success/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span> Connected
                    </span>
                @endif
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Outlook Inbox</h1>
            <p class="text-sm text-muted-foreground">
                @if($status['connected'])
                    Synced with {{ $status['email'] }}
                @else
                    Connect Microsoft Outlook to sync emails and auto-capture leads
                @endif
            </p>
        </div>

        @if($status['connected'])
            <div class="flex gap-2">
                <form action="{{ route('admin.outlook.sync') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2.5 text-sm transition-colors hover:border-gold/40">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        Sync Mail
                    </button>
                </form>
                <button onclick="document.getElementById('composeModal').classList.remove('hidden')" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Compose
                </button>
            </div>
        @endif
    </div>

    @if(!$status['connected'])
        <!-- Disconnected Banner / Call to Action -->
        <div class="glass-card mx-auto max-w-xl p-8 text-center rounded-2xl">
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-[#0F6CBD]/15 ring-1 ring-[#0F6CBD]/30">
                <svg viewBox="0 0 24 24" class="h-10 w-10 text-[#0F6CBD]" fill="currentColor">
                    <path d="M21.17 2.06A1.5 1.5 0 0122.5 3.5v17a1.5 1.5 0 01-1.33 1.44l-.17.06H14v-2h6V4h-6V2h7.17zM13.5 2v20L2 19V5l11.5-3zM9.25 7.5c-2.21 0-4 2.01-4 4.5s1.79 4.5 4 4.5 4-2.01 4-4.5-1.79-4.5-4-4.5zm0 2c1.1 0 2 1.12 2 2.5s-.9 2.5-2 2.5-2-1.12-2-2.5.9-2.5 2-2.5zM14 8v2h4V8h-4zm0 4v2h4v-2h-4zm0 4v2h4v-2h-4z"/>
                </svg>
            </div>

            <h2 class="font-display text-2xl font-semibold">Connect Microsoft Outlook</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Sign in with your Microsoft account to sync your inbox. Uses free Microsoft Graph API OAuth2 integration.
            </p>

            <div class="mt-6">
                <a href="{{ route('admin.outlook.connect') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#0F6CBD] to-[#0078D4] px-6 py-3 text-sm font-semibold text-white transition-all hover:shadow-lg hover:shadow-[#0F6CBD]/30">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Sign in with Microsoft
                </a>
            </div>
        </div>
    @else
        <!-- Filter Tabs & Actions -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @foreach([
                    'all' => 'All Mail',
                    'inbound' => 'Inbox',
                    'outbound' => 'Sent',
                    'unread' => 'Unread'
                ] as $k => $label)
                    <a href="{{ route('admin.outlook.index', ['filter' => $k, 'search' => $search]) }}" class="rounded-full border px-3.5 py-1.5 text-xs transition-all {{ $filter === $k ? 'border-gold/50 bg-gold/15 text-gold' : 'border-border bg-card/40 text-muted-foreground hover:border-gold/30 hover:text-foreground' }}">
                        {{ $label }}
                        @if($k === 'unread' && $unreadCount > 0)
                            <span class="ml-1 rounded-full bg-gold px-1.5 py-0.5 text-[9px] font-bold text-primary-foreground">{{ $unreadCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <form action="{{ route('admin.outlook.disconnect') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-muted-foreground hover:text-destructive transition-colors">
                    Disconnect Account
                </button>
            </form>
        </div>

        <!-- Inbox Grid -->
        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Email List -->
            <div class="glass-card rounded-xl overflow-hidden lg:col-span-2 flex flex-col">
                <!-- Search bar -->
                <div class="border-b border-border/60 p-3">
                    <form method="GET" action="{{ route('admin.outlook.index') }}">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <div class="relative">
                            <input type="text" name="search" value="{{ $search }}" placeholder="Search emails..." class="w-full rounded-xl border border-border bg-input/40 py-2 pl-3 pr-4 text-sm outline-none focus:border-gold/50" />
                        </div>
                    </form>
                </div>

                <!-- Messages -->
                <div class="max-h-[600px] overflow-y-auto divide-y divide-border/40">
                    @forelse($emails as $email)
                        <a href="{{ route('admin.outlook.index', ['email' => $email->id, 'filter' => $filter, 'search' => $search]) }}" class="block px-4 py-3 transition-colors hover:bg-gold/[0.04] {{ optional($selectedEmail)->id === $email->id ? 'bg-gold/[0.08] border-l-2 border-l-gold' : '' }} {{ !$email->is_read ? 'bg-card/50' : '' }}">
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $email->direction === 'outbound' ? 'bg-chart-3/20 text-chart-3' : 'bg-gradient-to-br from-gold/30 to-transparent text-gold' }}">
                                    {{ strtoupper(substr($email->direction === 'outbound' ? $email->to_email : ($email->from_name ?: $email->from_email), 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-sm {{ !$email->is_read ? 'font-semibold text-foreground' : 'font-medium text-foreground/80' }}">
                                            {{ $email->direction === 'outbound' ? 'To: '.$email->to_email : ($email->from_name ?: $email->from_email) }}
                                        </span>
                                        <span class="shrink-0 text-[10px] text-muted-foreground">{{ $email->received_at?->diffForHumans() }}</span>
                                    </div>
                                    <div class="mt-0.5 truncate text-xs font-medium text-foreground/70">{{ $email->subject ?: '(No Subject)' }}</div>
                                    <div class="mt-0.5 truncate text-[11px] text-muted-foreground">{{ $email->body_preview }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-muted-foreground">
                            No emails found.
                        </div>
                    @endforelse
                </div>

                @if($emails->hasPages())
                    <div class="p-3 border-t border-border/40">
                        {{ $emails->links() }}
                    </div>
                @endif
            </div>

            <!-- Email Detail Panel -->
            <div class="glass-card rounded-xl lg:col-span-3 min-h-[500px] p-6 flex flex-col justify-between">
                @if($selectedEmail)
                    <div>
                        <div class="border-b border-border/60 pb-4">
                            <h2 class="font-display text-2xl font-semibold">{{ $selectedEmail->subject ?: '(No Subject)' }}</h2>
                            <div class="mt-2 flex flex-wrap items-center justify-between text-sm text-muted-foreground">
                                <div>
                                    <span class="font-medium text-foreground">{{ $selectedEmail->from_name ?: $selectedEmail->from_email }}</span>
                                    &lt;{{ $selectedEmail->from_email }}&gt;
                                </div>
                                <div class="text-xs">{{ $selectedEmail->received_at?->format('d M Y, H:i') }}</div>
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">To: {{ $selectedEmail->to_email }}</div>
                        </div>

                        <div class="mt-4 text-sm text-foreground/85 whitespace-pre-wrap">
                            {!! $selectedEmail->body_html ?: e($selectedEmail->body_preview) !!}
                        </div>
                    </div>
                @else
                    <div class="flex h-full flex-col items-center justify-center text-center">
                        <svg class="mb-4 h-12 w-12 text-gold/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <h3 class="font-display text-lg font-semibold text-foreground/70">Select an email to view details</h3>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Compose Modal -->
<div id="composeModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
    <div class="glass-card w-full max-w-lg rounded-2xl p-6 relative">
        <button onclick="document.getElementById('composeModal').classList.add('hidden')" class="absolute right-4 top-4 text-muted-foreground hover:text-foreground">✕</button>
        <h2 class="font-display text-2xl font-semibold mb-4">Compose Email</h2>
        <form action="{{ route('admin.outlook.send') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">To</label>
                <input type="email" name="to" required class="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" placeholder="recipient@example.com" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Subject</label>
                <input type="text" name="subject" required class="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" placeholder="Subject..." />
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Message</label>
                <textarea name="body" rows="6" required class="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" placeholder="Write message..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('composeModal').classList.add('hidden')" class="rounded-xl border border-border px-4 py-2 text-xs font-medium">Cancel</button>
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground">Send</button>
            </div>
        </form>
    </div>
</div>
@endsection
