import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Plug, Check, Plus, Sparkles, Workflow, Mail, Bot, Video, Film, CreditCard, MapPin, ExternalLink } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { fetchIntegrations, toggleIntegration, getOutlookStatus, getOutlookAuthUrl, createVideoCallRoom } from "@/lib/api";
import { VideoCallModal } from "@/components/VideoCallModal";
import { VideoUploadModal } from "@/components/VideoUploadModal";

export const Route = createFileRoute("/app/integrations")({
  head: () => ({ meta: [{ title: "Integrations — Next Gen Relocation CRM" }] }),
  component: Integrations,
  validateSearch: (search) => {
    if (!search) return { connected: undefined };
    return {
      connected: search.connected === "true" ? true : search.connected === "false" ? false : undefined,
    };
  },
});

function Integrations() {
  const navigate = useNavigate();
  const [items, setItems] = useState<any[]>([]);
  const [outlookConnected, setOutlookConnected] = useState(false);
  const [showConnectToast, setShowConnectToast] = useState(false);
  const search = Route.useSearch();

  // Modal states for Video Call & Video Upload Features
  const [videoCallOpen, setVideoCallOpen] = useState(false);
  const [videoUploadOpen, setVideoUploadOpen] = useState(false);
  const [activeRoomUrl, setActiveRoomUrl] = useState<string>("");
  const [activeRoomCode, setActiveRoomCode] = useState<string>("");
  const [zegoConfig, setZegoConfig] = useState<{ appId?: number; appSign?: string; serverSecret?: string; isVideo?: boolean }>({});
  const [configModalIntegration, setConfigModalIntegration] = useState<any | null>(null);

  const loadIntegrations = () => {
    fetchIntegrations()
      .then((data) => setItems(data))
      .catch((err) => console.warn("Failed to fetch integrations from MySQL API:", err));
  };

  const loadOutlookStatus = () => {
    getOutlookStatus()
      .then((data) => setOutlookConnected(data.connected))
      .catch(() => {});
  };

  useEffect(() => {
    loadIntegrations();
    loadOutlookStatus();
  }, []);

  useEffect(() => {
    if (search.connected === true) {
      setOutlookConnected(true);
      setShowConnectToast(true);
      setTimeout(() => setShowConnectToast(false), 3000);
    } else if (search.connected === false) {
      setShowConnectToast(true);
      setTimeout(() => setShowConnectToast(false), 3000);
    }
  }, [search.connected]);

  const handleLaunchVideoCall = async () => {
    try {
      const room = await createVideoCallRoom(undefined, "General Virtual Survey");
      setActiveRoomUrl(room.roomUrl);
      setActiveRoomCode(room.roomCode);
      setZegoConfig({ appId: room.zegoAppId, appSign: room.zegoAppSign, serverSecret: room.zegoServerSecret, isVideo: true });
      setVideoCallOpen(true);
    } catch (err: any) {
      toast.error("Failed to launch video call room: " + err.message);
    }
  };

  const handleAction = async (item: any) => {
    if (item.id === "video-call") {
      await handleLaunchVideoCall();
      return;
    }

    if (item.id === "video-upload") {
      setVideoUploadOpen(true);
      return;
    }

    if (item.id === "outlook") {
      if (outlookConnected) {
        navigate({ to: "/app/outlook" });
      } else {
        try {
          const { url } = await getOutlookAuthUrl();
          window.location.href = url;
        } catch {
          navigate({ to: "/app/outlook" });
        }
      }
      return;
    }

    setConfigModalIntegration(item);
  };

  const handleToggleStatus = async (id: string) => {
    setItems((prev) => prev.map((i) => (i.id === id ? { ...i, connected: !i.connected } : i)));
    try {
      await toggleIntegration(id);
      loadIntegrations();
    } catch (err) {
      console.error("Error toggling integration in MySQL:", err);
    }
  };

  const displayItems = items.map((i) =>
    i.id === "outlook" ? { ...i, connected: outlookConnected } : i
  );

  const highlights = [
    { icon: Video, title: "WebRTC Live Video Calls", desc: "HD virtual surveys with screen share & 1-click customer invite links." },
    { icon: Film, title: "Video Survey Asset Storage", desc: "Drag & drop property walk-through recordings (.mp4) with playback controls." },
    { icon: Mail, title: "Outlook & Brevo Email Sync", desc: "Inbound emails captured as leads; outbound template tracking built-in." },
  ];

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
          <Plug className="h-3.5 w-3.5" /> Connections
        </div>
        <h1 className="mt-1 font-display text-4xl font-semibold">Integrations & App Hub</h1>
        <p className="text-sm text-muted-foreground">
          Manage HD Video Calls, Property Survey Video Uploads, Email Sync, AI & Payment Gateways.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        {highlights.map((h) => (
          <GlassCard key={h.title} className="p-5 border-gold/20">
            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold border border-gold/30">
              <h.icon className="h-5 w-5" />
            </div>
            <div className="font-display text-lg font-semibold">{h.title}</div>
            <p className="mt-1 text-sm text-muted-foreground">{h.desc}</p>
          </GlassCard>
        ))}
      </div>

      <div className="flex items-center justify-between">
        <h2 className="font-display text-xl font-semibold">Active Integrations ({displayItems.length})</h2>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {displayItems.map((i) => (
          <GlassCard key={i.id} hover className="flex flex-col p-5 border-gold/20 bg-[#0F1017]">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <div
                  className="flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold text-primary-foreground shadow-lg"
                  style={{ background: i.color || "#c9a84c" }}
                >
                  {i.id === "video-call" ? (
                    <Video className="h-5 w-5 text-white" />
                  ) : i.id === "video-upload" ? (
                    <Film className="h-5 w-5 text-white" />
                  ) : i.id === "outlook" ? (
                    <Mail className="h-5 w-5 text-white" />
                  ) : (
                    i.name.slice(0, 2)
                  )}
                </div>
                <div>
                  <div className="font-medium text-foreground text-sm">{i.name}</div>
                  <div className="text-xs text-gold uppercase tracking-wider font-mono">{i.category}</div>
                </div>
              </div>
              {i.connected && (
                <span className="flex items-center gap-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 px-2 py-0.5 text-[10px] font-bold text-emerald-400">
                  <Check className="h-3 w-3" /> Active
                </span>
              )}
            </div>

            <p className="mt-3 flex-1 text-xs text-muted-foreground leading-relaxed">{i.desc}</p>

            <div className="mt-4 flex items-center gap-2">
              <button
                onClick={() => handleAction(i)}
                className="flex-1 rounded-xl py-2 px-3 text-xs font-semibold bg-gradient-to-r from-gold-bright to-gold-dim text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
              >
                {i.id === "video-call"
                  ? "Launch Live Video Call →"
                  : i.id === "video-upload"
                  ? "Upload & Stream Videos →"
                  : i.id === "outlook"
                  ? i.connected
                    ? "Open Outlook Inbox →"
                    : "Connect with Microsoft"
                  : "Configure Integration →"}
              </button>

              <button
                onClick={() => handleToggleStatus(i.id)}
                className="rounded-xl p-2 border border-border/60 bg-card/60 text-muted-foreground hover:text-foreground text-xs"
                title={i.connected ? "Disconnect" : "Activate"}
              >
                {i.connected ? "Disable" : "Enable"}
              </button>
            </div>
          </GlassCard>
        ))}
      </div>

      <GlassCard className="flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center border-gold/25">
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold border border-gold/30">
          <Sparkles className="h-6 w-6" />
        </div>
        <div className="flex-1">
          <div className="font-display text-lg font-semibold">WebRTC + Cloud Video Storage Ready</div>
          <p className="text-sm text-muted-foreground">
            All virtual survey video calls, property recordings, email sync & AI automations persist state directly inside your MySQL database.
          </p>
        </div>
      </GlassCard>

      {/* Live Video Call Modal */}
      <VideoCallModal
        open={videoCallOpen}
        onClose={() => setVideoCallOpen(false)}
        roomUrl={activeRoomUrl}
        roomCode={activeRoomCode}
        zegoConfig={zegoConfig}
      />

      {/* Video Upload & Asset Storage Modal */}
      <VideoUploadModal
        open={videoUploadOpen}
        onClose={() => setVideoUploadOpen(false)}
      />

      {/* General Integration Config Modal */}
      {configModalIntegration && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-md rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-4">
            <div className="flex items-center gap-3 border-b border-border/60 pb-3">
              <div
                className="flex h-10 w-10 items-center justify-center rounded-xl font-bold text-white shadow-lg"
                style={{ background: configModalIntegration.color || "#c9a84c" }}
              >
                {configModalIntegration.name.slice(0, 2)}
              </div>
              <div>
                <h3 className="font-display text-base font-semibold text-foreground">
                  {configModalIntegration.name}
                </h3>
                <span className="text-[10px] text-gold uppercase tracking-wider font-mono">
                  {configModalIntegration.category}
                </span>
              </div>
            </div>

            <p className="text-xs text-muted-foreground leading-relaxed">
              {configModalIntegration.desc}
            </p>

            <div className="p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-xs font-semibold flex items-center gap-2">
              <Check className="h-4 w-4" /> Integration is active and syncing with MySQL DB.
            </div>

            <div className="flex justify-end pt-2">
              <button
                onClick={() => setConfigModalIntegration(null)}
                className="px-4 py-2 rounded-xl bg-gold/20 border border-gold/40 text-gold font-semibold text-xs hover:bg-gold/30"
              >
                Close Settings
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
