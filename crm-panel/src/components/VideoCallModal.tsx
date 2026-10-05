import { useState, useEffect, useRef, useCallback } from "react";
import { createPortal } from "react-dom";
import {
  Video,
  X,
  Copy,
  Check,
  PhoneOff,
  FileText,
  BellRing,
  Mail,
  ShieldCheck,
  Save,
  CheckCircle2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { adminVideoCallAction, saveVideoCallNotes } from "@/lib/api";

declare global {
  interface Window { ZegoUIKitPrebuilt?: any; }
}

const DEFAULT_ZEGO_APP_ID = 949748271;
const DEFAULT_ZEGO_SERVER_SECRET = "0b401e81a085bb92c949551ee96fb628";

/** Build the URL for the public static Zego call page */
function buildZegoUrl(roomID: string, userID: string, userName: string, appId: number, serverSecret: string, isVideo: boolean): string {
  const params = new URLSearchParams({
    roomID: roomID.trim(),
    userID: userID.trim(),
    userName: userName.trim(),
    appID: String(appId),
    serverSecret,
    isVideo: String(isVideo),
  });
  return `/crm/zego-call.html?${params.toString()}`;
}

interface VideoCallModalProps {
  open: boolean;
  onClose: () => void;
  roomUrl?: string;
  roomCode?: string;
  leadId?: string;
  leadName?: string;
  initialNotes?: string;
  onNotesSaved?: (notes: string) => void;
  zegoConfig?: { appId?: number; appSign?: string; serverSecret?: string; isVideo?: boolean };
  onCallEnded?: () => Promise<void> | void;
  callTitle?: string;
}

function VideoCallModalInner({
  open,
  onClose,
  roomCode = "NextGen-Survey-Room",
  leadId = "L-8855",
  leadName = "Virtual Survey",
  initialNotes = "",
  onNotesSaved,
  zegoConfig = {},
  onCallEnded,
  callTitle = "HD Virtual Survey · Video Call",
}: VideoCallModalProps) {
  const [copied, setCopied] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [notes, setNotes] = useState(initialNotes || "");
  const [isSaving, setIsSaving] = useState(false);
  const [savedSuccess, setSavedSuccess] = useState(false);
  const [mobileTab, setMobileTab] = useState<"video" | "notes">("video");
  const [iframeSrc, setIframeSrc] = useState<string | null>(null);

  const iframeRef = useRef<HTMLIFrameElement | null>(null);

  // Sync initial notes whenever modal opens or initialNotes prop changes
  useEffect(() => {
    if (open) {
      setNotes(initialNotes || "");
    }
  }, [open, initialNotes]);

  // Build iframe source when modal opens with a valid roomCode
  useEffect(() => {
    if (!open) { setIframeSrc(null); return; }

    const targetRoomID = (roomCode || `ROOM-${leadId}`).trim();
    const userID = `admin_${Date.now()}`;
    const userName = "Admin Office";

    console.log("[VideoCall] Opening Zego room:", targetRoomID);
    setIframeSrc(buildZegoUrl(
      targetRoomID,
      userID,
      userName,
      zegoConfig.appId || DEFAULT_ZEGO_APP_ID,
      zegoConfig.serverSecret || DEFAULT_ZEGO_SERVER_SECRET,
      zegoConfig.isVideo !== false,
    ));
  }, [open, roomCode, leadId, zegoConfig]);

  const handleSaveNotes = useCallback(async (notesToSave?: string) => {
    const text = notesToSave !== undefined ? notesToSave : notes;
    if (!leadId) return;
    setIsSaving(true);
    try {
      await saveVideoCallNotes(leadId, text);
      {
        setSavedSuccess(true);
        setTimeout(() => setSavedSuccess(false), 2500);
        if (onNotesSaved) onNotesSaved(text);
      }
    } catch (err) {
      console.error("Failed to save video call notes:", err);
    } finally {
      setIsSaving(false);
    }
  }, [leadId, notes, onNotesSaved]);

  // Auto-save notes with 1.5s debounce while typing
  useEffect(() => {
    if (!open || !leadId) return;
    const timer = setTimeout(() => {
      if (notes !== (initialNotes || "")) {
        handleSaveNotes(notes);
      }
    }, 1500);
    return () => clearTimeout(timer);
  }, [notes, open, leadId, initialNotes, handleSaveNotes]);

  const handleEndCall = useCallback(() => {
    const currentLeadId = leadId;
    const currentNotes = notes;
    
    // Instantly hide modal & clear iframe src
    setIframeSrc(null);
    onClose();

    // Perform call cleanup and note saving in background
    (async () => {
      try {
        if (onCallEnded) {
          await onCallEnded();
        } else if (currentLeadId) {
          saveVideoCallNotes(currentLeadId, currentNotes).catch(() => {});
          adminVideoCallAction(currentLeadId, "end").catch(() => {});
        }
        if (onNotesSaved) onNotesSaved(currentNotes);
      } catch {}
    })();
  }, [leadId, onClose, notes, onNotesSaved, onCallEnded]);

  // Listen for leave event from iframe
  useEffect(() => {
    const handler = (e: MessageEvent) => {
      if (e.data?.type === "zego_leave") { handleEndCall(); }
    };
    window.addEventListener("message", handler);
    return () => window.removeEventListener("message", handler);
  }, [handleEndCall]);

  if (!open) return null;

  const handleCopyLink = () => {
    navigator.clipboard.writeText(
      `${window.location.origin}/api/customer/video-call/verify?lead_id=${leadId}`
    );
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleSendInviteEmail = async () => {
    try { await fetch(`/api/leads/${leadId}/send-app-credentials`, { method: "POST" }); } catch {}
    setEmailSent(true);
    setTimeout(() => setEmailSent(false), 3000);
  };

  return (
    <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/95 backdrop-blur-2xl animate-in fade-in duration-200">
      <div className="w-full h-[100dvh] sm:h-[92vh] sm:max-w-6xl sm:mx-4 flex flex-col rounded-none sm:rounded-3xl border-0 sm:border border-gold/40 shadow-2xl overflow-hidden bg-[#0A0B0E]">

        {/* ── Header ── */}
        <div className="px-3 py-2.5 sm:px-5 sm:py-3 border-b border-gold/20 bg-[#0F1017] flex items-center justify-between shrink-0 gap-2">
          <div className="flex items-center gap-2 sm:gap-3 min-w-0">
            <div className="p-2 rounded-xl bg-gold/20 border border-gold/30 text-gold shrink-0">
              <Video className="h-4 w-4 sm:h-5 sm:w-5" />
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="font-display text-sm sm:text-base font-semibold text-foreground truncate">
                  {callTitle}
                </h2>
                <span className="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 uppercase tracking-wider animate-pulse shrink-0">
                  <BellRing className="h-3 w-3" /> Live
                </span>
              </div>
              <p className="text-[11px] text-muted-foreground truncate mt-0.5">
                Room: <strong className="text-gold font-mono">{roomCode}</strong>
                {leadId && ` · ${leadName} (${leadId})`}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-1.5 shrink-0">
            <Button type="button" onClick={handleSendInviteEmail} size="sm" variant="outline"
              className="border-gold/30 text-gold hover:bg-gold/10 text-xs px-2 py-1 h-8 hidden md:flex">
              <Mail className="h-3.5 w-3.5 mr-1" />
              {emailSent ? "Sent!" : "Email Invite"}
            </Button>
            <Button type="button" onClick={handleCopyLink} size="sm" variant="outline"
              className="border-gold/30 text-gold hover:bg-gold/10 text-xs px-2.5 py-1 h-8">
              {copied ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5" />}
              <span className="hidden sm:inline ml-1.5">{copied ? "Copied!" : "Copy Link"}</span>
            </Button>
            <button onClick={handleEndCall}
              className="p-1.5 sm:p-2 rounded-xl border border-border/60 bg-card/60 text-muted-foreground hover:text-destructive hover:border-destructive/40 transition-all"
              title="End Call">
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* ── Mobile Tabs ── */}
        <div className="flex lg:hidden border-b border-border/60 bg-card/40 shrink-0">
          <button onClick={() => setMobileTab("video")}
            className={`flex-1 flex items-center justify-center gap-2 py-2.5 text-xs font-semibold transition-all ${mobileTab === "video" ? "border-b-2 border-gold text-gold bg-gold/10" : "text-muted-foreground"}`}>
            <Video className="h-4 w-4" /> Live Video
          </button>
          <button onClick={() => setMobileTab("notes")}
            className={`flex-1 flex items-center justify-center gap-2 py-2.5 text-xs font-semibold transition-all ${mobileTab === "notes" ? "border-b-2 border-gold text-gold bg-gold/10" : "text-muted-foreground"}`}>
            <FileText className="h-4 w-4" /> Survey Notes {notes && "•"}
          </button>
        </div>

        {/* ── Content ── */}
        <div className="flex-1 grid grid-cols-12 overflow-hidden min-h-0">

          {/* Iframe Video Area */}
          <div className={`col-span-12 lg:col-span-9 bg-[#050608] relative h-full ${mobileTab === "video" ? "flex" : "hidden lg:flex"} flex-col`}>
            {iframeSrc ? (
              <iframe
                ref={iframeRef}
                src={iframeSrc}
                allow="camera; microphone; display-capture; autoplay; clipboard-write"
                allowFullScreen
                className="w-full h-full flex-1 border-0"
                title="Zego Video Call"
              />
            ) : (
              <div className="flex-1 flex flex-col items-center justify-center space-y-4 bg-[#050608]">
                <div className="h-12 w-12 animate-spin rounded-full border-4 border-gold border-t-transparent" />
                <p className="text-sm text-gold font-semibold">Preparing video room...</p>
              </div>
            )}
          </div>

          {/* Survey Notes Sidebar */}
          <div className={`col-span-12 lg:col-span-3 border-l-0 lg:border-l border-gold/15 bg-[#0F1017] p-3 sm:p-4 flex flex-col h-full space-y-3 ${mobileTab === "notes" ? "flex" : "hidden lg:flex"}`}>
            <div className="flex items-center justify-between border-b border-border/60 pb-2.5">
              <span className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-1.5">
                <FileText className="h-4 w-4" /> Survey Notes
              </span>
              <div className="flex items-center gap-2">
                {savedSuccess && (
                  <span className="text-[10px] font-semibold text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 px-2 py-0.5 rounded-full flex items-center gap-1">
                    <CheckCircle2 className="h-3 w-3" /> Saved
                  </span>
                )}
                <Button
                  type="button"
                  onClick={() => handleSaveNotes()}
                  disabled={isSaving}
                  size="sm"
                  className="bg-gold/20 hover:bg-gold/30 border border-gold/40 text-gold text-[11px] px-2.5 py-1 h-7"
                >
                  <Save className="h-3 w-3 mr-1" />
                  {isSaving ? "Saving..." : "Save Notes"}
                </Button>
              </div>
            </div>
            <p className="text-xs text-muted-foreground leading-relaxed">
              Record room inventory, stairs, disassembly needs, and fragile items. Notes are auto-saved & retained in lead history.
            </p>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="e.g. 3x Double sofas, glass dining table (needs crate), narrow 2nd floor staircase..."
              className="flex-1 w-full rounded-2xl border border-border bg-input/40 p-3 text-xs outline-none focus:border-gold/50 text-foreground resize-none min-h-[140px]"
            />
            <div className="pt-2 border-t border-border/60 space-y-2 shrink-0">
              <div className="rounded-xl border border-gold/20 bg-gold/5 p-2.5 text-[11px] text-muted-foreground flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-gold shrink-0" />
                <span>Encrypted HD stream · Notes synced to Lead CRM record.</span>
              </div>
              <Button onClick={handleEndCall}
                className="w-full bg-destructive/20 border border-destructive/40 text-destructive hover:bg-destructive/30 font-semibold h-9 text-xs"
                size="sm">
                <PhoneOff className="h-4 w-4 mr-1.5" /> Save & End Call
              </Button>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}

/** Portal wrapper — renders into document.body to escape any stacking context */
export function VideoCallModal(props: VideoCallModalProps) {
  if (!props.open) return null;
  return createPortal(<VideoCallModalInner {...props} />, document.body);
}
