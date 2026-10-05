@extends('layouts.admin')

@section('title', 'Calls & Transcripts')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Call Intelligence
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Calls & Transcripts</h1>
            <p class="text-sm text-muted-foreground">Recorded calls, AI transcripts via self-hosted Whisper, and WebRTC calling.</p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="openUploadModal()" class="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2.5 text-sm font-semibold text-foreground transition-all hover:border-gold/40 hover:bg-card">
                <svg class="h-4 w-4 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Upload Recording
            </button>
            <button onclick="openCallModal()" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Start Browser Call
            </button>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $statuses = ['All', 'done', 'processing', 'pending', 'failed'];
                $currentStatus = request('status', 'All');
            @endphp
            @foreach($statuses as $st)
                <a href="{{ route('admin.calls.index', array_merge(request()->except('page'), ['status' => $st === 'All' ? null : $st])) }}"
                   class="rounded-full border px-3.5 py-1.5 text-xs transition-all {{ ($currentStatus === $st || ($st === 'All' && !request('status'))) ? 'border-gold/50 bg-gold/15 text-gold font-semibold' : 'border-border bg-card/40 text-muted-foreground hover:border-gold/30' }}">
                    {{ ucfirst($st) }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.calls.index') }}" class="relative min-w-[280px]">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
                name="q"
                value="{{ request('q') }}"
                placeholder="Search transcript text…"
                class="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
            />
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-2xl border border-border bg-card/40 backdrop-blur-md">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-card/60 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3.5">Contact / Parties</th>
                        <th class="px-5 py-3.5">Direction</th>
                        <th class="px-5 py-3.5">Duration</th>
                        <th class="px-5 py-3.5">Recording</th>
                        <th class="px-5 py-3.5">Transcript Status</th>
                        <th class="px-5 py-3.5">Transcript Snippet</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse($calls as $call)
                        <tr class="hover:bg-card/70 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-medium text-foreground">
                                    {{ $call->contact?->name ?: ($call->to_number ?: 'Direct Contact') }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ $call->contact?->phone ?: $call->from_number }} · {{ $call->started_at?->diffForHumans() ?: 'Recent' }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $call->direction === 'inbound' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' }}">
                                    {{ ucfirst($call->direction) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs font-mono text-muted-foreground">
                                @if($call->duration_seconds)
                                    {{ gmdate('i:s', $call->duration_seconds) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if(!empty($call->recording_path))
                                    <audio controls class="h-8 w-44" preload="none" src="{{ URL::temporarySignedRoute('api.calls.audio', now()->addHours(1), ['call' => $call->id]) }}"></audio>
                                @else
                                    <span class="text-xs text-muted-foreground">No audio</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($call->transcript_status === 'done')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-400 border border-emerald-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Transcribed
                                    </span>
                                @elseif($call->transcript_status === 'processing')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/15 px-2.5 py-1 text-xs font-semibold text-blue-400 border border-blue-500/30">
                                        <span class="h-1.5 w-1.5 animate-ping rounded-full bg-blue-400"></span> Processing...
                                    </span>
                                @elseif($call->transcript_status === 'failed')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-500/15 px-2.5 py-1 text-xs font-semibold text-red-400 border border-red-500/30" title="{{ $call->transcript_error }}">
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gold/15 px-2.5 py-1 text-xs font-semibold text-gold border border-gold/30">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 max-w-xs">
                                @if(!empty($call->transcript))
                                    <p class="truncate text-xs text-foreground/90 font-serif italic" title="{{ $call->transcript }}">
                                        "{{ Str::limit($call->transcript, 65) }}"
                                    </p>
                                @elseif($call->transcript_status === 'failed')
                                    <span class="text-xs text-red-400 truncate block">{{ Str::limit($call->transcript_error ?: 'Transcription failed', 40) }}</span>
                                @else
                                    <span class="text-xs text-muted-foreground">Pending transcription</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right space-x-2">
                                @if($call->transcript_status === 'failed')
                                    <form method="POST" action="{{ route('admin.calls.retry', $call) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-gold hover:underline">Retry</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.calls.show', $call) }}" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground transition-all hover:border-gold/40 hover:text-gold">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-muted-foreground">
                                No call records found. Click "Start Browser Call" or "Upload Recording" to begin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($calls->hasPages())
            <div class="border-t border-border p-4">
                {{ $calls->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: Upload Existing Audio Recording -->
<!-- ======================================================== -->
<div id="upload-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-gold/30 bg-[#0F121D] p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-border">
            <h3 class="font-display text-lg font-semibold text-foreground">Upload Call Recording</h3>
            <button onclick="closeUploadModal()" class="text-muted-foreground hover:text-foreground">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.calls.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Select Contact (Optional)</label>
                <select name="contact_id" class="w-full rounded-xl border border-border bg-input/40 p-2.5 text-sm text-foreground outline-none focus:border-gold/50">
                    <option value="">-- No Contact (Ad-hoc Phone Call) --</option>
                    @foreach($contacts as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Audio Recording File</label>
                <input type="file" name="recording" required accept=".mp3,.m4a,.wav,.webm,.ogg,.amr,audio/*" class="w-full rounded-xl border border-border bg-input/40 p-2 text-sm text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-gold/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gold hover:file:bg-gold/30" />
                <p class="mt-1 text-[11px] text-muted-foreground">Supported formats: MP3, M4A, WAV, WEBM, OGG, AMR (Max 100MB)</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Duration in Seconds (Optional)</label>
                <input type="number" name="duration_seconds" placeholder="e.g. 120" min="0" class="w-full rounded-xl border border-border bg-input/40 p-2.5 text-sm text-foreground outline-none focus:border-gold/50" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeUploadModal()" class="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-xs font-bold text-primary-foreground hover:brightness-110">Upload & Transcribe</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: WebRTC Browser Call (With Audio Mixing & Recording) -->
<!-- ======================================================== -->
<div id="call-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-md">
    <div class="w-full max-w-lg rounded-2xl border border-gold/40 bg-[#0F121D] p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-border">
            <h3 class="font-display text-lg font-semibold text-foreground flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-gold animate-pulse"></span>
                Browser Call & Audio Recording (WebRTC)
            </h3>
            <button onclick="closeCallModal()" class="text-muted-foreground hover:text-foreground">✕</button>
        </div>

        <div id="call-setup-pane" class="mt-4 space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Select Contact to Call</label>
                <select id="agent-contact-select" class="w-full rounded-xl border border-border bg-input/40 p-2.5 text-sm text-foreground outline-none focus:border-gold/50">
                    @foreach($contacts as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                    @endforeach
                </select>
            </div>

            <div class="rounded-xl border border-gold/30 bg-gold/10 p-3 text-xs text-foreground/90">
                <strong class="text-gold block mb-1">⚠️ Dual-Sided Recording Activated</strong>
                Both your microphone and the contact's audio are mixed and recorded via Web Audio API. When the call ends, the audio is sent to the Whisper microservice for instant transcription.
            </div>

            <button type="button" onclick="initiateBrowserCall()" class="w-full rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim py-3 text-sm font-bold text-primary-foreground hover:brightness-110">
                Start Call Session & Generate Link
            </button>
        </div>

        <!-- Active Call Controller -->
        <div id="call-active-pane" class="mt-4 space-y-4 hidden text-center">
            <div class="rounded-xl border border-border bg-card/40 p-4 text-left">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-gold mb-1">Contact Join Link</label>
                <div class="flex items-center gap-2">
                    <input id="contact-join-url" readonly class="w-full rounded-lg border border-border bg-input/60 px-3 py-1.5 text-xs text-foreground font-mono" />
                    <button onclick="copyJoinUrl()" class="shrink-0 rounded-lg bg-gold/20 border border-gold/40 px-3 py-1.5 text-xs font-semibold text-gold hover:bg-gold/30">Copy</button>
                </div>
                <p class="text-[11px] text-muted-foreground mt-1">Send this link to the contact to connect their browser.</p>
            </div>

            <div class="py-2">
                <span id="agent-call-status" class="inline-block rounded-full bg-gold/20 border border-gold/40 px-3 py-1 text-xs font-semibold text-gold">Waiting for contact to join...</span>
                <div id="agent-timer" class="text-3xl font-bold font-mono text-foreground mt-2">00:00</div>
            </div>

            <div class="flex items-center justify-center gap-3 pt-2">
                <button onclick="toggleAgentMute()" id="agent-mute-btn" class="rounded-xl border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground hover:bg-card/80">Mute Mic</button>
                <button onclick="endAgentCall()" class="rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white hover:bg-red-700">End & Transcribe Call</button>
            </div>
        </div>
    </div>
</div>

<audio id="agent-remote-audio" autoplay playsinline></audio>

<!-- PeerJS WebRTC Library -->
<script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>

<script>
    function openUploadModal() {
        document.getElementById('upload-modal').classList.remove('hidden');
        document.getElementById('upload-modal').classList.add('flex');
    }
    function closeUploadModal() {
        document.getElementById('upload-modal').classList.add('hidden');
        document.getElementById('upload-modal').classList.remove('flex');
    }
    function openCallModal() {
        document.getElementById('call-modal').classList.remove('hidden');
        document.getElementById('call-modal').classList.add('flex');
    }
    function closeCallModal() {
        if (agentActiveCallId) {
            if (!confirm('A call is in progress. End call and save recording?')) return;
            endAgentCall();
        }
        document.getElementById('call-modal').classList.add('hidden');
        document.getElementById('call-modal').classList.remove('flex');
    }
    function copyJoinUrl() {
        const input = document.getElementById('contact-join-url');
        input.select();
        navigator.clipboard.writeText(input.value);
        alert('Contact join link copied to clipboard!');
    }

    // Agent WebRTC State
    let agentPeer = null;
    let agentLocalStream = null;
    let agentCall = null;
    let agentActiveCallId = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let agentCallTimer = null;
    let agentSeconds = 0;
    let audioContext = null;
    let mixedDestination = null;

    async function initiateBrowserCall() {
        const contactId = document.getElementById('agent-contact-select').value;

        // 1. Create Call record on server
        const res = await fetch('/api/calls', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ contact_id: contactId, direction: 'outbound' })
        });
        const data = await res.json();
        if (!data.success) {
            alert('Failed to initiate call session.');
            return;
        }

        agentActiveCallId = data.call.id;
        const roomCode = data.roomCode;
        document.getElementById('contact-join-url').value = data.joinUrl;
        document.getElementById('call-setup-pane').classList.add('hidden');
        document.getElementById('call-active-pane').classList.remove('hidden');

        // 2. Request Agent Microphone
        try {
            agentLocalStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });

            // Initialize PeerJS
            const agentPeerId = "agent_" + roomCode;
            agentPeer = new Peer(agentPeerId, {
                debug: 1,
                config: {
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:global.stun.twilio.com:3478' }
                    ]
                }
            });

            agentPeer.on('open', (id) => {
                document.getElementById('agent-call-status').innerText = 'Ready. Waiting for contact to open link...';
            });

            // Listen for contact calling in
            agentPeer.on('call', (call) => {
                agentCall = call;
                call.answer(agentLocalStream);
                setupCallRecording(call);
            });

        } catch (err) {
            alert('Error accessing microphone: ' + err.message);
            closeCallModal();
        }
    }

    function setupCallRecording(call) {
        call.on('stream', (remoteStream) => {
            document.getElementById('agent-call-status').innerText = 'Connected & Recording (Mixed Audio)';
            document.getElementById('agent-remote-audio').srcObject = remoteStream;

            // Start timer
            startAgentTimer();

            // Set up Web Audio API mixing: Mix Local Mic + Remote Audio
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                mixedDestination = audioContext.createMediaStreamDestination();

                const localSource = audioContext.createMediaStreamSource(agentLocalStream);
                const remoteSource = audioContext.createMediaStreamSource(remoteStream);

                localSource.connect(mixedDestination);
                remoteSource.connect(mixedDestination);

                // Record mixed stream with MediaRecorder
                recordedChunks = [];
                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
                mediaRecorder = new MediaRecorder(mixedDestination.stream, { mimeType: mimeType });

                mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) recordedChunks.push(e.data);
                };

                mediaRecorder.onstop = uploadAgentRecording;

                mediaRecorder.start(1000); // 1-second chunks
                console.log('[WebRTC Recording] Dual-audio stream recording started.');
            } catch (recErr) {
                console.warn('[WebRTC Recording] AudioContext mixing notice:', recErr);
            }
        });
    }

    function startAgentTimer() {
        if (agentCallTimer) return;
        agentCallTimer = setInterval(() => {
            agentSeconds++;
            const mins = String(Math.floor(agentSeconds / 60)).padStart(2, '0');
            const secs = String(agentSeconds % 60).padStart(2, '0');
            document.getElementById('agent-timer').innerText = `${mins}:${secs}`;
        }, 1000);
    }

    function toggleAgentMute() {
        if (!agentLocalStream) return;
        const track = agentLocalStream.getAudioTracks()[0];
        if (track) {
            track.enabled = !track.enabled;
            document.getElementById('agent-mute-btn').innerText = track.enabled ? 'Mute Mic' : 'Unmute Mic';
        }
    }

    function endAgentCall() {
        if (agentCallTimer) clearInterval(agentCallTimer);
        document.getElementById('agent-call-status').innerText = 'Uploading recording & queuing Whisper...';

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        } else if (recordedChunks.length > 0) {
            uploadAgentRecording();
        } else {
            cleanupAgentState();
            window.location.reload();
        }
    }

    async function uploadAgentRecording() {
        if (!agentActiveCallId || recordedChunks.length === 0) {
            cleanupAgentState();
            window.location.reload();
            return;
        }

        const audioBlob = new Blob(recordedChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('recording', audioBlob, `call_${agentActiveCallId}.webm`);
        formData.append('duration_seconds', agentSeconds);

        try {
            const res = await fetch(`/api/calls/${agentActiveCallId}/recording`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                alert('Call ended and recording uploaded! Transcription is now processing.');
            }
        } catch (e) {
            console.error('Failed to upload recording:', e);
        } finally {
            cleanupAgentState();
            window.location.reload();
        }
    }

    function cleanupAgentState() {
        if (agentCall) agentCall.close();
        if (agentPeer) agentPeer.destroy();
        if (agentLocalStream) {
            agentLocalStream.getTracks().forEach(t => t.stop());
        }
        if (audioContext && audioContext.state !== 'closed') {
            audioContext.close();
        }
    }
</script>
@endsection
