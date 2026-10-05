<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Voice Call | Next Gen Relocation</title>
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #07090e; color: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #0f121d; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 24px; max-width: 480px; width: 100%; padding: 36px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7); }
        .logo { color: #d4af37; font-size: 22px; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 20px; }
        .avatar { width: 88px; height: 88px; border-radius: 50%; background: rgba(212, 175, 55, 0.15); border: 2px solid #d4af37; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; color: #d4af37; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 6px; }
        p.subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 24px; }
        .notice-box { background: rgba(212, 175, 55, 0.1); border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 14px; padding: 14px 16px; margin-bottom: 24px; text-align: left; font-size: 13px; color: #e5e7eb; line-height: 1.5; }
        .notice-box strong { color: #d4af37; display: block; margin-bottom: 4px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
        .btn-join { width: 100%; background: linear-gradient(135deg, #d4af37, #aa820a); color: #000; border: none; border-radius: 14px; padding: 16px; font-size: 16px; font-weight: 700; cursor: pointer; transition: transform 0.1s, opacity 0.2s; box-shadow: 0 4px 20px rgba(212,175,55,0.3); }
        .btn-join:hover { opacity: 0.95; transform: translateY(-1px); }
        .btn-end { background: #dc2626; color: #fff; border: none; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .btn-mute { background: #374151; color: #fff; border: none; border-radius: 12px; padding: 12px 20px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 20px; margin-right: 10px; }
        .active-call-pane { display: none; }
        .pulsing-wave { display: inline-flex; align-items: center; gap: 4px; height: 24px; margin-bottom: 12px; }
        .pulsing-wave span { width: 4px; height: 16px; background: #d4af37; border-radius: 2px; animation: wave 1s ease-in-out infinite; }
        .pulsing-wave span:nth-child(2) { animation-delay: 0.2s; height: 24px; }
        .pulsing-wave span:nth-child(3) { animation-delay: 0.4s; height: 18px; }
        .pulsing-wave span:nth-child(4) { animation-delay: 0.6s; height: 22px; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.4); } 50% { transform: scaleY(1); } }
        .timer { font-size: 28px; font-weight: 700; color: #d4af37; font-variant-numeric: tabular-nums; margin: 12px 0; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">✨ Next Gen Relocation</div>

        <!-- Consent & Pre-join Screen -->
        <div id="prejoin-pane">
            <div class="avatar">📞</div>
            <h1>Direct Voice Call</h1>
            <p class="subtitle">Agent is ready to connect with you.</p>

            <div class="notice-box">
                <strong>⚠️ Recording & Transcription Notice</strong>
                This call is recorded and automatically transcribed with AI for quality assurance and customer record keeping.
            </div>

            <button class="btn-join" id="btn-accept-join" onclick="acceptAndJoin()">
                Accept & Join Call
            </button>
        </div>

        <!-- Connected Call Screen -->
        <div id="active-pane" class="active-call-pane">
            <span class="status-badge" id="call-status">Connecting...</span>
            <div class="pulsing-wave">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="timer" id="call-timer">00:00</div>
            <p style="font-size: 13px; color: #9ca3af;">Call with Next Gen Relocation Office</p>

            <div>
                <button class="btn-mute" id="btn-mute" onclick="toggleMute()">Mute Mic</button>
                <button class="btn-end" onclick="hangUp()">Hang Up</button>
            </div>
        </div>

        <!-- Ended Screen -->
        <div id="ended-pane" style="display: none;">
            <div class="avatar" style="border-color: #9ca3af; color: #9ca3af;">✓</div>
            <h1>Call Ended</h1>
            <p class="subtitle">Thank you for speaking with Next Gen Relocation.</p>
        </div>
    </div>

    <!-- Hidden audio element for remote stream -->
    <audio id="remote-audio" autoplay playsinline></audio>

    <script>
        const roomCode = "{{ $roomCode }}";
        let localStream = null;
        let peer = null;
        let currentCall = null;
        let timerInterval = null;
        let seconds = 0;
        let isMuted = false;

        async function acceptAndJoin() {
            document.getElementById('prejoin-pane').style.display = 'none';
            document.getElementById('active-pane').style.display = 'block';

            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });

                // Initialize PeerJS client with unique contact peer ID
                const contactPeerId = "contact_" + roomCode;
                const agentPeerId = "agent_" + roomCode;

                peer = new Peer(contactPeerId, {
                    debug: 1,
                    config: {
                        iceServers: [
                            { urls: 'stun:stun.l.google.com:19302' },
                            { urls: 'stun:global.stun.twilio.com:3478' }
                        ]
                    }
                });

                peer.on('open', (id) => {
                    console.log('[PeerJS] Contact connected with ID:', id);
                    document.getElementById('call-status').innerText = 'Calling Agent...';

                    // Call the agent's peer ID
                    const call = peer.call(agentPeerId, localStream);
                    handleCall(call);
                });

                peer.on('call', (call) => {
                    // In case agent called contact first
                    call.answer(localStream);
                    handleCall(call);
                });

                peer.on('error', (err) => {
                    console.warn('[PeerJS] Error:', err);
                    document.getElementById('call-status').innerText = 'Reconnecting...';
                });

            } catch (err) {
                alert('Microphone access is required to join the call: ' + err.message);
                document.getElementById('prejoin-pane').style.display = 'block';
                document.getElementById('active-pane').style.display = 'none';
            }
        }

        function handleCall(call) {
            currentCall = call;

            call.on('stream', (remoteStream) => {
                document.getElementById('call-status').innerText = 'Call in Progress (Recorded)';
                const remoteAudio = document.getElementById('remote-audio');
                remoteAudio.srcObject = remoteStream;
                startTimer();
            });

            call.on('close', () => {
                hangUp();
            });
        }

        function startTimer() {
            if (timerInterval) return;
            timerInterval = setInterval(() => {
                seconds++;
                const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                const secs = String(seconds % 60).padStart(2, '0');
                document.getElementById('call-timer').innerText = `${mins}:${secs}`;
            }, 1000);
        }

        function toggleMute() {
            if (!localStream) return;
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                isMuted = !audioTrack.enabled;
                document.getElementById('btn-mute').innerText = isMuted ? 'Unmute Mic' : 'Mute Mic';
            }
        }

        function hangUp() {
            if (timerInterval) clearInterval(timerInterval);
            if (currentCall) currentCall.close();
            if (peer) peer.destroy();
            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
            }

            document.getElementById('active-pane').style.display = 'none';
            document.getElementById('ended-pane').style.display = 'block';
        }
    </script>
</body>
</html>
