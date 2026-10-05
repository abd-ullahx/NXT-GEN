import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { PhoneCall, PhoneOff, Video } from "lucide-react";
import { VideoCallModal } from "@/components/VideoCallModal";
import { chatCallAction, fetchIncomingChatCall } from "@/lib/api";
import { getStoredToken } from "@/lib/auth";

export function IncomingChatCallBanner() {
  const [call, setCall] = useState<any | null>(null);
  const [active, setActive] = useState(false);

  useEffect(() => {
    const API_BASE = import.meta.env.VITE_API_URL || "/api";

    const ensureCallCredentials = async (callData: any | null) => {
      if (!callData) return callData;
      if (callData.zegoAppId && callData.zegoServerSecret) return callData;
      try {
        const token = getStoredToken();
        const res = await fetch(`${API_BASE}/chat/calls/${callData.id}/status`, {
          headers: {
            "ngrok-skip-browser-warning": "true",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });
        if (res.ok) {
          const data = await res.json();
          const refreshed = data?.call;
          if (refreshed?.zegoAppId && refreshed?.zegoServerSecret) {
            return refreshed;
          }
        }
      } catch {
        // Ignore; return original call data
      }
      return callData;
    };

    const check = async () => {
      if (active) return;
      try {
        const data = await fetchIncomingChatCall();
        const callData = data.call || null;
        const enriched = await ensureCallCredentials(callData);
        setCall(enriched);
      } catch {
        // Ignore transient polling failures.
      }
    };
    check();
    const timer = window.setInterval(check, 10000);
    return () => window.clearInterval(timer);
  }, [active]);

  const answer = async () => {
    if (!call) return;
    try {
      await chatCallAction(call.id, "accept");
      setCall({ ...call, status: "in_progress" });
      setActive(true);
    } catch (err) {
      console.warn("[IncomingChatCallBanner] Failed to accept call:", err);
    }
  };

  const decline = () => {
    if (!call) return;
    const callToDecline = call;
    setCall(null);
    chatCallAction(callToDecline.id, "decline").catch(() => {});
  };

  const banner = call && !active ? (
    <div className="fixed top-5 right-5 z-[99999] pointer-events-auto animate-in slide-in-from-top-5 duration-300">
      <div className="flex items-center gap-4 rounded-2xl border-2 border-gold/60 bg-[#0F1017] p-4 text-foreground shadow-2xl backdrop-blur-xl">
        <div className="relative flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-gold border border-gold/40 shrink-0">
          {call.isVideo ? (
            <Video className="h-6 w-6 animate-pulse text-gold" />
          ) : (
            <PhoneCall className="h-6 w-6 animate-pulse text-gold" />
          )}
          <span className="absolute -top-1 -right-1 flex h-4 w-4">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-gold opacity-75"></span>
            <span className="relative inline-flex rounded-full h-4 w-4 bg-gold"></span>
          </span>
        </div>

        <div>
          <span className="text-[10px] font-bold uppercase tracking-wider text-gold bg-gold/20 px-2 py-0.5 rounded-full border border-gold/30">
            Incoming {call.isVideo ? "Video" : "Audio"} Call
          </span>
          <h4 className="font-display text-base font-semibold text-foreground mt-0.5">
            {call.callerName || "Team Member"}
          </h4>
          <p className="text-xs text-muted-foreground">Direct message call</p>
        </div>

        <div className="flex items-center gap-2 ml-2 shrink-0">
          <button
            type="button"
            onClick={decline}
            className="cursor-pointer pointer-events-auto flex items-center gap-1 rounded-xl bg-destructive/20 border border-destructive/40 px-3.5 py-2 text-xs font-semibold text-destructive hover:bg-destructive/30 transition-all"
          >
            <PhoneOff className="h-4 w-4" /> Decline
          </button>
          <button
            type="button"
            onClick={answer}
            className="cursor-pointer pointer-events-auto flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-xs font-bold text-primary-foreground shadow-[var(--shadow-gold)] hover:brightness-110 transition-all"
          >
            {call.isVideo ? <Video className="h-4 w-4" /> : <PhoneCall className="h-4 w-4" />} Answer
          </button>
        </div>
      </div>
    </div>
  ) : null;

  return (
    <>
      {createPortal(banner, document.body)}
      <VideoCallModal
        open={active}
        onClose={() => {
          setActive(false);
          setCall(null);
        }}
        roomCode={call?.roomId}
        leadId=""
        leadName={call?.callerName || "Team Member"}
        callTitle={`Team ${call?.isVideo ? "Video" : "Audio"} Call`}
        zegoConfig={{
          appId: call?.zegoAppId,
          appSign: call?.zegoAppSign,
          // backend returns 'zegoServerSecret' in the call object from present()
          serverSecret: call?.zegoServerSecret,
          isVideo: call?.isVideo,
        }}
        onCallEnded={() => {
          const callToEnd = call;
          setActive(false);
          setCall(null);
          if (callToEnd) {
            chatCallAction(callToEnd.id, "end").catch(() => {});
          }
        }}
      />
    </>
  );
}