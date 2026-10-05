import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { PhoneCall, PhoneOff, Video, Phone } from "lucide-react";
import { VideoCallModal } from "@/components/VideoCallModal";
import { adminVideoCallAction, fetchIncomingChatCall, chatCallAction } from "@/lib/api";
import { getStoredToken } from "@/lib/auth";

const API_BASE = import.meta.env.VITE_API_URL || "/api";

export function IncomingCallBanner() {
  const [incomingCall, setIncomingCall] = useState<any | null>(null);
  const [activeCallModalOpen, setActiveCallModalOpen] = useState(false);
  const [roomUrl, setRoomUrl] = useState("");
  const [roomCode, setRoomCode] = useState("");
  const [activeLeadId, setActiveLeadId] = useState("");
  const [activeLeadName, setActiveLeadName] = useState("");
  const [activeChatCallId, setActiveChatCallId] = useState<number | null>(null);
  const [isChatCall, setIsChatCall] = useState(false);
  const [zegoConfig, setZegoConfig] = useState<{ appId?: number; appSign?: string; serverSecret?: string; isVideo?: boolean }>({});

  // Poll for both Lead Video Calls (/api/customer/video-call/verify) AND Team Chat Calls (/api/chat/calls/incoming)
  useEffect(() => {
    let isMounted = true;
    const checkIncomingCall = async () => {
      // If modal is already active, don't re-trigger banner
      if (activeCallModalOpen) return;

      try {
        const token = getStoredToken();

        // 1. Check Flow B: Lead Video Call
        const leadRes = await fetch(`${API_BASE}/customer/video-call/verify`, {
          headers: {
            "ngrok-skip-browser-warning": "true",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });
        if (leadRes.ok) {
          const leadData = await leadRes.json();
          if (isMounted && leadData && leadData.isRinging && leadData.status === "ringing") {
            setIncomingCall({ ...leadData, isChatCall: false });
            return;
          }
        }

        // 2. Check Flow C: Team Chat Call (requires Bearer token)
        if (token) {
          try {
            const chatData = await fetchIncomingChatCall();
            const call = chatData?.call;
            if (isMounted && call && call.status === "ringing") {
              setIncomingCall({
                isChatCall: true,
                callId: call.id,
                callerName: call.callerName || "Team Staff",
                roomId: call.roomId,
                isVideo: call.isVideo ?? true,
                zegoAppId: call.zegoAppId,
                zegoAppSign: call.zegoAppSign,
                zegoServerSecret: call.zegoServerSecret,
              });
              return;
            }
          } catch {}
        }

        if (isMounted) {
          setIncomingCall(null);
        }
      } catch (err) {
        // ignore network error during polling
      }
    };

    checkIncomingCall();
    const interval = setInterval(checkIncomingCall, 10000);
    return () => {
      isMounted = false;
      clearInterval(interval);
    };
  }, [activeCallModalOpen]);

  const handleAnswerCall = async (e?: React.MouseEvent) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (!incomingCall) return;

    if (incomingCall.isChatCall) {
      // Flow C: Answer Team Chat Call
      const callId = incomingCall.callId;
      const callerName = incomingCall.callerName || "Team Member";
      const roomId = incomingCall.roomId;

      setRoomCode(roomId);
      setActiveLeadId("");
      setActiveLeadName(callerName);
      setActiveChatCallId(callId);
      setIsChatCall(true);
      setZegoConfig({
        appId: incomingCall.zegoAppId,
        appSign: incomingCall.zegoAppSign,
        serverSecret: incomingCall.zegoServerSecret,
        isVideo: incomingCall.isVideo ?? true,
      });
      setActiveCallModalOpen(true);
      setIncomingCall(null);

      try {
        await chatCallAction(callId, "accept");
      } catch (err) {
        console.warn("Background chat call answer note:", err);
      }
    } else {
      // Flow B: Answer Lead Video Call
      const leadId = incomingCall.leadId || incomingCall.lead_id || "L-101";
      const callerName = incomingCall.callerName || incomingCall.caller_name || incomingCall.clientName || incomingCall.client_name || "Surveyor";
      const roomId = incomingCall.roomId || incomingCall.room_id || `ROOM-${leadId}`;

      setRoomCode(roomId);
      setActiveLeadId(leadId);
      setActiveLeadName(callerName);
      setActiveChatCallId(null);
      setIsChatCall(false);
      setZegoConfig({
        appId: incomingCall.zegoAppId || incomingCall.zego_app_id,
        appSign: incomingCall.zegoAppSign || incomingCall.zego_app_sign,
        serverSecret: incomingCall.zegoServerSecret || incomingCall.zego_server_secret,
        isVideo: incomingCall.isVideo ?? incomingCall.is_video ?? true,
      });
      setActiveCallModalOpen(true);
      setIncomingCall(null);

      try {
        await adminVideoCallAction(leadId, "accept");
      } catch (err) {
        console.warn("Background lead call answer note:", err);
      }
    }
  };

  const handleDeclineCall = (e?: React.MouseEvent) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (!incomingCall) return;

    if (incomingCall.isChatCall) {
      const callId = incomingCall.callId;
      setIncomingCall(null);
      if (callId) {
        chatCallAction(callId, "decline").catch(() => {});
      }
    } else {
      const leadId = incomingCall.leadId;
      setIncomingCall(null);
      if (leadId) {
        adminVideoCallAction(leadId, "decline").catch(() => {});
      }
    }
  };

  const banner = incomingCall && !activeCallModalOpen ? (
    <div className="fixed top-5 right-5 z-[99999] pointer-events-auto animate-in slide-in-from-top-5 duration-300">
      <div className="flex items-center gap-4 rounded-2xl border-2 border-gold/60 bg-[#0F1017] p-4 text-foreground shadow-2xl backdrop-blur-xl">
        <div className="relative flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-gold border border-gold/40 shrink-0">
          <PhoneCall className="h-6 w-6 animate-pulse text-gold" />
          <span className="absolute -top-1 -right-1 flex h-4 w-4">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-gold opacity-75"></span>
            <span className="relative inline-flex rounded-full h-4 w-4 bg-gold"></span>
          </span>
        </div>

        <div>
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-bold uppercase tracking-wider text-gold bg-gold/20 px-2 py-0.5 rounded-full border border-gold/30">
              {incomingCall.isChatCall ? (incomingCall.isVideo ? "Incoming Video Call" : "Incoming Voice Call") : "Incoming Lead Call"}
            </span>
            {incomingCall.leadId && <span className="text-xs font-mono text-muted-foreground">{incomingCall.leadId}</span>}
          </div>
          <h4 className="font-display text-base font-semibold text-foreground mt-0.5">
            {incomingCall.callerName || incomingCall.clientName || "Incoming Caller"}
          </h4>
          <p className="text-xs text-muted-foreground">
            {incomingCall.isChatCall ? "Team member is calling you..." : "Surveyor requested live video walkthrough"}
          </p>
        </div>

        <div className="flex items-center gap-2 ml-2 shrink-0">
          <button
            type="button"
            onClick={handleDeclineCall}
            className="cursor-pointer pointer-events-auto relative z-[10000] flex items-center gap-1 rounded-xl bg-destructive/20 border border-destructive/40 px-3.5 py-2 text-xs font-semibold text-destructive hover:bg-destructive/30 transition-all"
            title="Decline Call"
          >
            <PhoneOff className="h-4 w-4" /> Decline
          </button>
          <button
            type="button"
            onClick={handleAnswerCall}
            className="cursor-pointer pointer-events-auto relative z-[10000] flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-xs font-bold text-primary-foreground shadow-[var(--shadow-gold)] hover:brightness-110 transition-all"
            title="Answer Call"
          >
            {incomingCall.isVideo ? <Video className="h-4 w-4" /> : <Phone className="h-4 w-4" />} Answer
          </button>
        </div>
      </div>
    </div>
  ) : null;

  return (
    <>
      {/* Floating Banner rendered via Portal to escape header stacking context */}
      {createPortal(banner, document.body)}

      {/* Video Call Modal (also uses Portal internally) */}
      <VideoCallModal
        open={activeCallModalOpen}
        onClose={() => setActiveCallModalOpen(false)}
        roomUrl={roomUrl}
        roomCode={roomCode}
        leadId={activeLeadId}
        leadName={activeLeadName}
        zegoConfig={zegoConfig}
        callTitle={isChatCall ? "Team Call" : "HD Virtual Survey · Video Call"}
        onCallEnded={() => {
          setActiveCallModalOpen(false);
          if (isChatCall && activeChatCallId) {
            chatCallAction(activeChatCallId, "end").catch(() => {});
          } else if (activeLeadId) {
            adminVideoCallAction(activeLeadId, "end").catch(() => {});
          }
        }}
      />
    </>
  );
}

