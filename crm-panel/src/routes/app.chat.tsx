import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState, useRef, useCallback } from "react";
import { toast } from "sonner";
import { useConfirm } from "@/contexts/ConfirmContext";
import {
  Hash,
  Lock,
  Send,
  Users,
  Search,
  MessageSquare,
  Sparkles,
  Loader2,
  Circle,
  Plus,
  X,
  Menu,
  ChevronLeft,
  Pencil,
  Trash2,
  Paperclip,
  FileImage,
  Check,
  AlertTriangle,
  Download,
  FileText,
  ExternalLink,
  Video,
  Phone,
  PhoneOff,
  PhoneIncoming,
  PhoneOutgoing,
  PhoneMissed,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useAuth } from "@/lib/auth";
import {
  fetchChatChannels,
  fetchChatMembers,
  fetchChatMessages,
  sendChatMessage,
  sendChatMessageWithAttachment,
  createChatChannel, deleteChatChannel,
  updateChatMessage,
  deleteChatMessage,
  startChatCall,
  chatCallAction,
  fetchChatCallStatus,
} from "@/lib/api";
import { VideoCallModal } from "@/components/VideoCallModal";

export const Route = createFileRoute("/app/chat")({
  head: () => ({
    meta: [{ title: "Team Chat & Collaboration — Next Gen Relocation CRM" }],
  }),
  component: TeamChatPage,
});

interface Channel {
  id: number;
  name: string;
  display_name: string;
  description: string | null;
  is_private: boolean;
}

interface TeamMember {
  id: number;
  name: string;
  email: string;
  role: string;
  unreadCount: number;
  isOnline: boolean;
}

interface MessageItem {
  id: number;
  channelId: number | null;
  senderId: number;
  receiverId: number | null;
  message: string;
  attachmentUrl: string | null;
  attachmentName?: string | null;
  attachmentMime?: string | null;
  isRead: boolean;
  createdAt: string;
  updatedAt?: string;
  isEdited?: boolean;
  isPending?: boolean;
  senderName: string;
  senderRole: string;
  senderEmail: string;
}

function TeamChatPage() {
  const confirm = useConfirm();
  const { user } = useAuth();
  const [channels, setChannels] = useState<Channel[]>([]);
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [activeType, setActiveType] = useState<"channel" | "dm">("channel");
  const [activeChannelId, setActiveChannelId] = useState<number | null>(null);
  const [activeMemberId, setActiveMemberId] = useState<number | null>(null);
  const [messages, setMessages] = useState<MessageItem[]>([]);
  const [inputMessage, setInputMessage] = useState("");
  const [attachment, setAttachment] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messageCacheRef = useRef<Record<string, MessageItem[]>>({});
  const isNearBottomRef = useRef(true);

  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [hasMoreOlder, setHasMoreOlder] = useState(true);
  const [query, setQuery] = useState("");
  const [activeChatCall, setActiveChatCall] = useState<any | null>(null);
  const [startingCallType, setStartingCallType] = useState<"audio" | "video" | null>(null);
  const callStartLockRef = useRef(false);

  // Mobile sidebar visibility toggle
  const [showSidebar, setShowSidebar] = useState(true);

  // Edit / Delete state
  const [editingMsgId, setEditingMsgId] = useState<number | null>(null);
  const [editText, setEditText] = useState("");
  const [deletingMsgId, setDeletingMsgId] = useState<number | null>(null);

  const handleEditSave = async (msgId: number) => {
    const trimmed = editText.trim();
    if (!trimmed) return;
    try {
      const updated = await updateChatMessage(msgId, trimmed);
      setMessages((prev) =>
        prev.map((m) =>
          m.id === msgId ? { ...m, message: updated.message, isEdited: true } : m
        )
      );
    } catch (err: any) {
      toast.error("Could not edit message: " + err.message);
    } finally {
      setEditingMsgId(null);
      setEditText("");
    }
  };

  const handleDeleteConfirm = async (msgId: number) => {
    try {
      await deleteChatMessage(msgId);
      setMessages((prev) => prev.filter((m) => m.id !== msgId));
    } catch (err: any) {
      toast.error("Could not delete message: " + err.message);
    } finally {
      setDeletingMsgId(null);
    }
  };

  // Group Channel Creation state
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [newGroupName, setNewGroupName] = useState("");
  const [newGroupDesc, setNewGroupDesc] = useState("");
  const [isNewGroupPrivate, setIsNewGroupPrivate] = useState(false);
  const [newGroupMembers, setNewGroupMembers] = useState<number[]>([]);
  const [creatingGroup, setCreatingGroup] = useState(false);

  const handleDeleteChannel = async (id: number, name: string) => {
    if (!await confirm(`Are you sure you want to delete the group "${name}"? This action cannot be undone.`)) return;
    try {
      await deleteChatChannel(id);
      setChannels((prev) => prev.filter((c) => c.id !== id));
      if (activeChannelId === id) {
        setActiveChannelId(null);
        setActiveType("channel");
      }
    } catch (err: any) {
      toast.error("Failed to delete group: " + (err.message || "Unknown error"));
    }
  };

  const handleCreateGroupSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newGroupName.trim() || creatingGroup) return;

    setCreatingGroup(true);
    try {
      const createdChan = await createChatChannel({
        displayName: newGroupName.trim(),
        description: newGroupDesc.trim() || undefined,
        isPrivate: isNewGroupPrivate,
        members: newGroupMembers,
      });

      setChannels((prev) => [...prev, createdChan]);
      setActiveType("channel");
      setActiveChannelId(createdChan.id);
      setActiveMemberId(null);
      setIsCreateModalOpen(false);
      setNewGroupName("");
      setNewGroupDesc("");
      setIsNewGroupPrivate(false);
      setNewGroupMembers([]);
    } catch (err: any) {
      toast.error("Failed to create group: " + (err.message || "Unknown error"));
    } finally {
      setCreatingGroup(false);
    }
  };

  // Scroll to bottom helper
  const scrollToBottom = (smooth = true) => {
    messagesEndRef.current?.scrollIntoView({ behavior: smooth ? "smooth" : "auto" });
  };

  const getThreadKey = useCallback(() => {
    return activeType === "channel" ? `channel-${activeChannelId}` : `dm-${activeMemberId}`;
  }, [activeType, activeChannelId, activeMemberId]);

  // Initial load of channels and team members
  useEffect(() => {
    let isMounted = true;
    Promise.all([fetchChatChannels(), fetchChatMembers()])
      .then(([chanData, memData]) => {
        if (!isMounted) return;
        const validChannels = Array.isArray(chanData) ? chanData : [];
        const validMembers = Array.isArray(memData) ? memData : [];
        setChannels(validChannels);
        setMembers(validMembers);
        if (validChannels.length > 0) {
          setActiveChannelId(validChannels[0].id);
          setActiveType("channel");
        }
      })
      .catch((err) => console.error("Failed to load chat bootstrap:", err))
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  // Fast switching + Delta Polling for live messages
  useEffect(() => {
    if (!activeChannelId && !activeMemberId) return;

    const threadKey = getThreadKey();
    const cached = messageCacheRef.current[threadKey];

    // INSTANT SWITCH: If cached, load immediately from memory with 0 delay!
    if (cached && cached.length > 0) {
      setMessages(cached);
      setLoading(false);
      requestAnimationFrame(() => scrollToBottom(false));
    } else {
      setLoading(true);
    }
    setHasMoreOlder(true);

    const params =
      activeType === "channel"
        ? { channelId: activeChannelId!, limit: 25 }
        : { receiverId: activeMemberId!, limit: 25 };

    // Initial thread load
    fetchChatMessages(params)
      .then((msgData) => {
        const list = Array.isArray(msgData) ? msgData : [];
        setMessages(list);
        messageCacheRef.current[threadKey] = list;
        requestAnimationFrame(() => scrollToBottom(false));
      })
      .catch((err) => console.error("Error fetching initial thread messages:", err))
      .finally(() => setLoading(false));

    // Polling interval for live delta sync
    const interval = setInterval(() => {
      const currentMsgs = messageCacheRef.current[threadKey] || [];
      const positiveIds = currentMsgs.filter((m) => m.id > 0).map((m) => m.id);
      const maxId = positiveIds.length > 0 ? Math.max(...positiveIds) : undefined;

      const pollParams =
        activeType === "channel"
          ? { channelId: activeChannelId!, afterId: maxId }
          : { receiverId: activeMemberId!, afterId: maxId };

      Promise.all([fetchChatMessages(pollParams), fetchChatMembers()])
        .then(([newMsgs, memData]) => {
          if (memData) {
            const updatedMembers = memData.map((m: TeamMember) =>
              activeType === "dm" && m.id === activeMemberId ? { ...m, unreadCount: 0 } : m
            );
            setMembers(updatedMembers);
          }

          // DELTA UPDATE: Only update state if new messages arrived!
          if (Array.isArray(newMsgs) && newMsgs.length > 0) {
            setMessages((prev) => {
              const existingIds = new Set(prev.map((m) => m.id));
              const brandNew = newMsgs.filter((m: MessageItem) => !existingIds.has(m.id));
              if (brandNew.length === 0) return prev;

              const updated = [...prev, ...brandNew];
              messageCacheRef.current[threadKey] = updated.slice(-50); // Keep last 50 in cache
              return updated;
            });

            if (isNearBottomRef.current) {
              requestAnimationFrame(() => scrollToBottom(true));
            }
          }
        })
        .catch((err) => console.error("Error polling chat:", err));
    }, 2500);

    return () => clearInterval(interval);
  }, [activeType, activeChannelId, activeMemberId, getThreadKey]);

  // Load older historical messages on scroll to top
  const handleLoadOlder = async () => {
    if (loadingOlder || !hasMoreOlder || messages.length === 0) return;
    const positiveIds = messages.filter((m) => m.id > 0).map((m) => m.id);
    if (positiveIds.length === 0) return;

    const minId = Math.min(...positiveIds);
    if (!minId || minId <= 1) {
      setHasMoreOlder(false);
      return;
    }

    setLoadingOlder(true);
    const container = messagesContainerRef.current;
    const oldScrollHeight = container?.scrollHeight || 0;

    try {
      const params =
        activeType === "channel"
          ? { channelId: activeChannelId!, beforeId: minId, limit: 20 }
          : { receiverId: activeMemberId!, beforeId: minId, limit: 20 };

      const olderMsgs = await fetchChatMessages(params);
      if (!Array.isArray(olderMsgs) || olderMsgs.length === 0) {
        setHasMoreOlder(false);
      } else {
        if (olderMsgs.length < 20) setHasMoreOlder(false);

        const threadKey = getThreadKey();
        setMessages((prev) => {
          const existingIds = new Set(prev.map((m) => m.id));
          const freshOlder = olderMsgs.filter((m: MessageItem) => !existingIds.has(m.id));
          const updated = [...freshOlder, ...prev];
          messageCacheRef.current[threadKey] = updated;
          return updated;
        });

        // Maintain exact scroll position
        requestAnimationFrame(() => {
          if (container) {
            container.scrollTop = container.scrollHeight - oldScrollHeight;
          }
        });
      }
    } catch (err) {
      console.error("Failed to load older messages:", err);
    } finally {
      setLoadingOlder(false);
    }
  };

/** Fast non-blocking helper to create a lightweight image thumbnail for instant UI preview */
async function createQuickThumbnailUrl(file: File): Promise<string> {
  if (!file.type.startsWith("image/")) return file.name;

  return new Promise((resolve) => {
    const img = document.createElement("img");
    const url = URL.createObjectURL(file);
    img.onload = () => {
      const canvas = document.createElement("canvas");
      const maxDim = 300;
      let w = img.width;
      let h = img.height;
      if (w > maxDim || h > maxDim) {
        if (w > h) {
          h = Math.round((h * maxDim) / w);
          w = maxDim;
        } else {
          w = Math.round((w * maxDim) / h);
          h = maxDim;
        }
      }
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext("2d");
      ctx?.drawImage(img, 0, 0, w, h);
      URL.revokeObjectURL(url);
      canvas.toBlob(
        (blob) => {
          resolve(blob ? URL.createObjectURL(blob) : file.name);
        },
        "image/jpeg",
        0.7
      );
    };
    img.onerror = () => resolve(file.name);
    img.src = url;
  });
}

  // Optimistic Instant Message Sending (Background Task Queue)
  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    const text = inputMessage.trim();
    if (!text && !attachment) return;

    const currentAttachment = attachment;
    const tempId = -Date.now();
    const threadKey = getThreadKey();

    // Reset input fields IMMEDIATELY (0ms UI lag!)
    setInputMessage("");
    setAttachment(null);
    if (fileInputRef.current) fileInputRef.current.value = "";

    // Generate fast lightweight preview URL for images
    let previewUrl: string | null = null;
    if (currentAttachment) {
      if (currentAttachment.type.startsWith("image/")) {
        previewUrl = await createQuickThumbnailUrl(currentAttachment);
      } else {
        previewUrl = currentAttachment.name; // Keep filename for docs/videos during background upload
      }
    }

    // 1. Create optimistic local message
    const tempMsg: MessageItem = {
      id: tempId,
      channelId: activeType === "channel" ? activeChannelId : null,
      senderId: user?.id || 0,
      receiverId: activeType === "dm" ? activeMemberId : null,
      message: text,
      attachmentUrl: previewUrl,
      isRead: true,
      createdAt: new Date().toISOString(),
      senderName: user?.name || "Me",
      senderRole: user?.role || "staff",
      senderEmail: user?.email || "",
      isPending: true,
    };

    // 2. Instantly update UI with zero delay!
    setMessages((prev) => {
      const updated = [...prev, tempMsg];
      messageCacheRef.current[threadKey] = updated;
      return updated;
    });
    scrollToBottom(true);

    // 3. Launch background upload task (non-blocking!)
    (async () => {
      try {
        let newMsg: MessageItem;
        if (currentAttachment) {
          const formData = new FormData();
          if (activeType === "channel") formData.append("channelId", String(activeChannelId));
          else formData.append("receiverId", String(activeMemberId));
          formData.append("message", text);
          formData.append("attachment", currentAttachment);
          newMsg = await sendChatMessageWithAttachment(formData);
        } else {
          const payload =
            activeType === "channel"
              ? { channelId: activeChannelId!, message: text }
              : { receiverId: activeMemberId!, message: text };
          newMsg = await sendChatMessage(payload);
        }

        // Clean up blob thumbnail URL if created
        if (previewUrl && previewUrl.startsWith("blob:")) {
          URL.revokeObjectURL(previewUrl);
        }

        // Replace optimistic tempMsg with server response
        setMessages((prev) => {
          const updated = prev.map((m) => (m.id === tempId ? newMsg : m));
          messageCacheRef.current[threadKey] = updated;
          return updated;
        });
      } catch (err: any) {
        toast.error("Failed to send message: " + (err.message || "Unknown error"));
        setMessages((prev) => prev.filter((m) => m.id !== tempId));
      }
    })();
  };

  const activeChannel = channels.find((c) => c.id === activeChannelId);
  const activeMember = members.find((m) => m.id === activeMemberId);

  /** outgoing call that is ringing but not yet in_progress */
  const [ringingCall, setRingingCall] = useState<any | null>(null);
  const ringingPollRef = useRef<number | null>(null);

  const stopRingingPoll = () => {
    if (ringingPollRef.current) {
      window.clearInterval(ringingPollRef.current);
      ringingPollRef.current = null;
    }
  };

  const handleStartChatCall = async (isVideo: boolean) => {
    if (!activeMember || callStartLockRef.current || activeChatCall || ringingCall) return;
    callStartLockRef.current = true;
    setStartingCallType(isVideo ? "video" : "audio");
    try {
      const response = await startChatCall(activeMember.id, isVideo);
      const call = response.call;
      if (!call) throw new Error("Invalid call response from server");

      // Show ringing overlay — do NOT open Zego yet
      setRingingCall(call);

      // Poll until recipient accepts, declines, or call times out
      const TERMINAL = ["in_progress", "declined", "missed", "ended", "completed", "failed"];
      ringingPollRef.current = window.setInterval(async () => {
        try {
          const data = await fetchChatCallStatus(call.id);
          const status: string = data?.call?.status ?? data?.status ?? "ringing";

          if (status === "in_progress") {
            stopRingingPoll();
            setRingingCall(null);
            // Now open Zego with the same call data
            setActiveChatCall({ ...call, ...data.call });
          } else if (TERMINAL.includes(status) && status !== "in_progress") {
            stopRingingPoll();
            setRingingCall(null);
            if (status === "declined") toast.error("Call was declined.");
            else if (status === "missed") toast.info("No answer — call timed out.");
          }
        } catch {
          // Ignore transient poll errors
        }
      }, 2000);
    } catch (err: any) {
      toast.error(err.message || "Could not start call");
    } finally {
      callStartLockRef.current = false;
      setStartingCallType(null);
    }
  };

  const handleCancelRinging = () => {
    stopRingingPoll();
    const callToEnd = ringingCall;
    setRingingCall(null);
    if (callToEnd) {
      chatCallAction(callToEnd.id, "end").catch(() => {});
    }
  };

  const filteredMembers = members.filter(
    (m) =>
      m.name.toLowerCase().includes(query.toLowerCase()) ||
      m.role.toLowerCase().includes(query.toLowerCase())
  );

  const getRoleBadgeStyle = (role: string) => {
    switch (role?.toLowerCase()) {
      case "admin":
        return "bg-amber-400/10 text-amber-400 border-amber-400/30";
      case "manager":
        return "bg-purple-400/10 text-purple-400 border-purple-400/30";
      case "surveyor":
        return "bg-cyan-400/10 text-cyan-400 border-cyan-400/30";
      case "driver":
        return "bg-emerald-400/10 text-emerald-400 border-emerald-400/30";
      default:
        return "bg-gold/10 text-gold border-gold/30";
    }
  };

  return (
    <div className="space-y-3 sm:space-y-4 h-[calc(100vh-100px)] flex flex-col">
      {/* Top Title Banner */}
      <div className="flex items-center justify-between shrink-0 px-1">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Sparkles className="h-3.5 w-3.5" /> Internal Collaboration
          </div>
          <h1 className="mt-0.5 font-display text-2xl sm:text-3xl font-semibold text-foreground">
            Team Chat & Channels
          </h1>
          <p className="text-xs text-muted-foreground hidden sm:block">
            Real-time messaging exclusively for internal staff, managers, surveyors & drivers.
          </p>
        </div>
      </div>

      {/* Main Layout Container */}
      <GlassCard className="flex-1 overflow-hidden relative border-gold/25 p-0 bg-[#0A0B0E] flex">
        
        {/* Left Sidebar: Channels & Direct Messages */}
        {/* Left Sidebar: Channels & Direct Messages */}
        <div className={`${
          showSidebar ? "flex" : "hidden md:flex"
        } absolute inset-0 z-30 md:relative md:z-auto w-full md:w-80 lg:w-72 xl:w-80 shrink-0 border-r border-gold/15 flex-col bg-[#0F1017]`}>
          
          {/* Workspace Banner */}
          <div className="p-4 border-b border-gold/15 bg-[#121319] flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="p-2 rounded-xl bg-gold/15 border border-gold/30 text-gold">
                <MessageSquare className="h-4 w-4" />
              </div>
              <div>
                <div className="text-xs font-bold tracking-wide gold-text">NEXT GEN TEAM WORKSPACE</div>
                <div className="text-[10px] text-muted-foreground uppercase tracking-wider">Internal Channels</div>
              </div>
            </div>
          </div>

          {/* Search Bar */}
          <div className="p-3 border-b border-border/40">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
              <input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search channels or team…"
                className="w-full rounded-xl border border-border bg-input/40 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
              />
            </div>
          </div>

          {/* Scrollable Channels & DMs List */}
          <div className="flex-1 overflow-y-auto p-3 space-y-6">
            
            {/* CHANNELS SECTION */}
            <div className="space-y-1">
              <div className="px-2 text-[10px] font-bold uppercase tracking-widest text-gold/80 flex items-center justify-between">
                <span>CHANNELS ({channels.length})</span>
                {user?.role === "admin" && (
                  <button
                    type="button"
                    onClick={() => setIsCreateModalOpen(true)}
                    className="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-gold/15 text-gold border border-gold/30 hover:bg-gold/30 transition-all font-semibold text-[10px]"
                  >
                    <Plus className="h-3 w-3" /> Group
                  </button>
                )}
              </div>
              <div className="space-y-0.5 mt-1">
                {channels.map((chan) => {
                  const isSelected = activeType === "channel" && activeChannelId === chan.id;
                  return (
                    <button
                      key={chan.id}
                      onClick={() => {
                        setActiveType("channel");
                        setActiveChannelId(chan.id);
                        setActiveMemberId(null);
                        setShowSidebar(false);
                      }}
                      className={`group w-full flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs transition-all ${
                        isSelected
                          ? "bg-gold/15 border border-gold/40 text-foreground font-semibold shadow-[var(--shadow-gold)]"
                          : "text-muted-foreground hover:bg-card/40 hover:text-foreground"
                      }`}
                    >
                    <div className="flex items-center gap-2.5 truncate">
                      {chan.is_private ? (
                        <Lock className="h-3.5 w-3.5 text-amber-400 shrink-0" />
                      ) : (
                        <Hash className="h-3.5 w-3.5 text-gold shrink-0" />
                      )}
                      <span className="truncate">{chan.display_name}</span>
                    </div>
                    {user?.role === "admin" && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDeleteChannel(chan.id, chan.display_name);
                        }}
                        className="text-muted-foreground hover:text-red-500 shrink-0 transition-colors"
                        title="Delete Group"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </button>
                  );
                })}
              </div>
            </div>

            {/* DIRECT MESSAGES SECTION */}
            <div className="space-y-1">
              <div className="px-2 text-[10px] font-bold uppercase tracking-widest text-gold/80 flex items-center justify-between">
                <span>DIRECT MESSAGES ({filteredMembers.length})</span>
              </div>
              <div className="space-y-0.5 mt-1">
                {filteredMembers.map((mem) => {
                  const isSelected = activeType === "dm" && activeMemberId === mem.id;
                  return (
                    <button
                      key={mem.id}
                      onClick={() => {
                        setActiveType("dm");
                        setActiveMemberId(mem.id);
                        setActiveChannelId(null);
                        setShowSidebar(false);
                        // Optimistically clear the unread count instantly
                        setMembers((prev) => prev.map(m => m.id === mem.id ? { ...m, unreadCount: 0 } : m));
                      }}
                      className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-all ${
                        isSelected
                          ? "bg-gold/15 border border-gold/40 text-foreground font-semibold shadow-[var(--shadow-gold)]"
                          : "text-muted-foreground hover:bg-card/40 hover:text-foreground"
                      }`}
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <div className="relative shrink-0">
                          <div className="h-6.5 w-6.5 rounded-full bg-gradient-to-br from-gold/30 to-gold/5 border border-gold/30 flex items-center justify-center text-[10px] font-bold text-gold">
                            {mem.name ? mem.name[0].toUpperCase() : "U"}
                          </div>
                          <Circle
                            className={`absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 ${
                              mem.isOnline
                                ? "fill-emerald-500 text-emerald-500"
                                : "fill-slate-600 text-slate-600 opacity-60"
                            }`}
                          />
                        </div>
                        <div className="min-w-0 text-left">
                          <div className="truncate text-xs text-foreground font-medium flex items-center gap-1.5">
                            <span>{mem.name}</span>
                          </div>
                          <div className="text-[10px] text-muted-foreground uppercase font-mono flex items-center gap-1">
                            <span>{mem.role}</span>
                            <span>·</span>
                            <span className={mem.isOnline ? "text-emerald-400 font-semibold" : "text-muted-foreground/70"}>
                              {mem.isOnline ? "Online" : "Offline"}
                            </span>
                          </div>
                        </div>
                      </div>

                      {mem.unreadCount > 0 && (
                        <span className="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gold text-black shrink-0">
                          {mem.unreadCount}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>

          </div>

          {/* User Status Footer */}
          <div className="p-3 border-t border-gold/15 bg-[#121319] flex items-center gap-3">
            <div className="relative">
              <div className="h-8 w-8 rounded-full bg-gold/20 border border-gold/40 flex items-center justify-center font-bold text-gold text-xs">
                {user?.name ? user.name[0].toUpperCase() : "U"}
              </div>
              <Circle className="absolute bottom-0 right-0 h-2.5 w-2.5 fill-emerald-500 text-emerald-500" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-xs font-semibold text-foreground truncate">{user?.name || "Logged Staff"}</div>
              <div className="text-[10px] text-gold uppercase font-mono">{user?.role || "staff"}</div>
            </div>
          </div>
        </div>

        {/* Right Main Chat Thread Area */}
        {/* Right Main Chat Thread Area */}
        <div className={`${
          showSidebar ? "hidden md:flex" : "flex"
        } flex-1 flex-col bg-[#07080B] min-h-0 h-full overflow-hidden relative`}>
          
          {/* Top Chat Header Bar */}
          <div className="px-3 sm:px-6 py-3 sm:py-3.5 border-b border-gold/20 bg-[#0F1017] flex items-center justify-between shrink-0 z-20 shadow-lg">
            <div className="flex items-center gap-2 sm:gap-3.5 min-w-0">
              {/* Mobile back button */}
              <button
                onClick={() => setShowSidebar(true)}
                className="md:hidden flex items-center justify-center h-9 w-9 rounded-xl border border-gold/30 bg-gold/10 text-gold shrink-0"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              {activeType === "channel" ? (
                <>
                  <div className="p-2.5 rounded-2xl bg-gold/10 border border-gold/30 text-gold shadow-md shrink-0">
                    {activeChannel?.is_private ? <Lock className="h-5 w-5 text-amber-400" /> : <Hash className="h-5 w-5" />}
                  </div>
                  <div className="min-w-0">
                    <h2 className="text-base font-bold text-foreground flex items-center gap-2 truncate">
                      #{activeChannel?.display_name || "general"}
                    </h2>
                    <p className="text-xs text-muted-foreground truncate">
                      {activeChannel?.description || "Internal team discussion channel"}
                    </p>
                  </div>
                </>
              ) : (
                <>
                  <div className="relative shrink-0">
                    <div className="h-10 w-10 rounded-full bg-gradient-to-br from-gold/40 to-gold/10 border border-gold/50 flex items-center justify-center font-bold text-gold text-sm shadow-md">
                      {activeMember?.name ? activeMember.name[0].toUpperCase() : "U"}
                    </div>
                    <Circle
                      className={`absolute bottom-0 right-0 h-3 w-3 ${
                        activeMember?.isOnline
                          ? "fill-emerald-500 text-emerald-500"
                          : "fill-slate-600 text-slate-600 opacity-60"
                      }`}
                    />
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <h2 className="text-base font-bold text-foreground truncate">
                        {activeMember?.name || "Team Member"}
                      </h2>
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider ${getRoleBadgeStyle(activeMember?.role || "staff")}`}>
                        {activeMember?.role || "STAFF"}
                      </span>
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold border ${
                        activeMember?.isOnline
                          ? "bg-emerald-500/15 text-emerald-400 border-emerald-500/30"
                          : "bg-muted/20 text-muted-foreground border-border/50"
                      }`}>
                        {activeMember?.isOnline ? "🟢 ONLINE" : "⚪ OFFLINE"}
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5 truncate">
                      {activeMember?.email || "internal.staff@nextgen.co.uk"}
                      {(activeMember as any)?.lastSeenText && ` · ${(activeMember as any).lastSeenText}`}
                    </p>
                  </div>
                </>
              )}
            </div>

            <div className="flex items-center gap-3 shrink-0">
              {activeType === "dm" && activeMember && (
                <>
                  <button
                      type="button"
                      onClick={() => handleStartChatCall(false)}
                      disabled={Boolean(startingCallType) || Boolean(activeChatCall) || Boolean(ringingCall)}
                      className="p-2 rounded-xl border border-border/60 bg-card/60 hover:bg-emerald-500/10 hover:border-emerald-500/40 hover:text-emerald-400 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                      title={`Call ${activeMember.name}`}
                    >
                      {startingCallType === "audio" ? <Loader2 className="h-4 w-4 animate-spin" /> : <Phone className="h-4 w-4" />}
                    </button>
                  <button
                      type="button"
                      onClick={() => handleStartChatCall(true)}
                      disabled={Boolean(startingCallType) || Boolean(activeChatCall) || Boolean(ringingCall)}
                      className="p-2 rounded-xl border border-border/60 bg-card/60 hover:bg-gold/10 hover:border-gold/40 hover:text-gold transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                      title={`Video call ${activeMember.name}`}
                    >
                      {startingCallType === "video" ? <Loader2 className="h-4 w-4 animate-spin" /> : <Video className="h-4 w-4" />}
                    </button>
                </>
              )}
              <div className="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gold/20 bg-gold/5 text-xs text-gold font-mono">
                <Users className="h-3.5 w-3.5" />
                <span>Internal Staff Chat</span>
              </div>
            </div>
          </div>

          {/* Messages Stream Container */}
          <div
            ref={messagesContainerRef}
            onScroll={() => {
              const el = messagesContainerRef.current;
              if (!el) return;
              isNearBottomRef.current = el.scrollHeight - el.scrollTop - el.clientHeight < 150;
              if (el.scrollTop < 20 && !loadingOlder && hasMoreOlder && messages.length > 0) {
                handleLoadOlder();
              }
            }}
            className="flex-1 p-3 sm:p-6 overflow-y-auto space-y-4 bg-[#07080B] flex flex-col justify-between min-h-0"
          >
            {loadingOlder && (
              <div className="flex items-center justify-center py-2 text-xs text-gold">
                <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> Loading older history…
              </div>
            )}
            {loading ? (
              <div className="flex items-center justify-center my-auto py-12 text-muted-foreground text-sm">
                <Loader2 className="h-5 w-5 animate-spin mr-2 text-gold" /> Loading messages…
              </div>
            ) : messages.length === 0 ? (
              <div className="flex flex-col items-center justify-center my-auto py-12 text-center text-muted-foreground p-8">
                <div className="p-4 rounded-full bg-gold/10 border border-gold/20 text-gold mb-3">
                  <MessageSquare className="h-8 w-8" />
                </div>
                <h3 className="text-base font-semibold text-foreground">No messages yet</h3>
                <p className="text-xs mt-1 max-w-sm text-muted-foreground">
                  Start the discussion in {activeType === "channel" ? `#${activeChannel?.display_name || "general"}` : activeMember?.name || "this thread"}. Only internal staff can view this chat.
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {messages.map((msg, index) => {
                  const isMe = msg.senderId === user?.id || msg.senderEmail === user?.email;
                  const isBeingEdited = editingMsgId === msg.id;
                  const isBeingDeleted = deletingMsgId === msg.id;

                  return (
                    <div
                      key={msg.id || index}
                      className={`group flex gap-2 sm:gap-3 max-w-[90%] sm:max-w-2xl ${isMe ? "ml-auto flex-row-reverse" : ""} ${msg.isPending ? "opacity-60 transition-opacity" : ""}`}
                    >
                      {/* Avatar */}
                      <div className="h-8 w-8 rounded-full bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/30 flex items-center justify-center font-bold text-gold text-xs shrink-0 mt-1">
                        {msg.senderName ? msg.senderName[0].toUpperCase() : "U"}
                      </div>

                      <div className={`space-y-1 min-w-0 flex-1 ${isMe ? "items-end text-right" : ""}`}>
                        {/* Sender meta row */}
                        <div className={`flex items-center gap-2 text-[11px] ${isMe ? "justify-end" : ""}`}>
                          <span className="font-semibold text-foreground">{msg.senderName}</span>
                          <span className={`px-1.5 py-0.5 rounded-full text-[9px] font-bold border uppercase ${getRoleBadgeStyle(msg.senderRole)}`}>
                            {msg.senderRole}
                          </span>
                          <span className="font-mono text-[11px] font-semibold text-emerald-200/90 bg-black/40 px-1.5 py-0.5 rounded border border-emerald-500/30">
                            {msg.createdAt ? new Date(msg.createdAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "Just now"}
                          </span>
                          {msg.isPending && (
                            <span className="text-[9px] italic text-gold font-medium flex items-center gap-1"><Loader2 className="h-2.5 w-2.5 animate-spin" /> Sending…</span>
                          )}
                          {msg.isEdited && (
                            <span className="text-[9px] italic text-muted-foreground/70">(edited)</span>
                          )}
                        </div>

                        {/* Delete confirmation mini-banner */}
                        {isBeingDeleted && (
                          <div className={`flex items-center gap-2 px-3 py-2 rounded-xl bg-red-500/10 border border-red-500/40 text-xs text-red-400 ${isMe ? "justify-end" : ""}`}>
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            <span>Delete this message?</span>
                            <button
                              onClick={() => handleDeleteConfirm(msg.id)}
                              className="px-2 py-0.5 rounded-lg bg-red-500 text-white text-[10px] font-bold hover:bg-red-600"
                            >Yes</button>
                            <button
                              onClick={() => setDeletingMsgId(null)}
                              className="px-2 py-0.5 rounded-lg bg-card border border-border text-[10px] font-bold hover:text-foreground"
                            >Cancel</button>
                          </div>
                        )}

                        {/* Inline edit input */}
                        {isBeingEdited ? (
                          <div className="flex items-center gap-2">
                            <input
                              autoFocus
                              value={editText}
                              onChange={(e) => setEditText(e.target.value)}
                              onKeyDown={(e) => {
                                if (e.key === "Enter") handleEditSave(msg.id);
                                if (e.key === "Escape") { setEditingMsgId(null); setEditText(""); }
                              }}
                              className="flex-1 rounded-xl border border-gold/50 bg-[#14151D] px-3 py-2 text-xs text-foreground outline-none focus:border-gold"
                            />
                            <button
                              onClick={() => handleEditSave(msg.id)}
                              className="p-2 rounded-xl bg-gold/20 border border-gold/40 text-gold hover:bg-gold/30"
                            ><Check className="h-3.5 w-3.5" /></button>
                            <button
                              onClick={() => { setEditingMsgId(null); setEditText(""); }}
                              className="p-2 rounded-xl bg-card border border-border text-muted-foreground hover:text-foreground"
                            ><X className="h-3.5 w-3.5" /></button>
                          </div>
                        ) : (
                          /* Normal message bubble with hover actions */
                          <div className={`relative flex ${isMe ? "justify-end" : "justify-start"}`}>
                            {/* Hover action buttons — only for own messages */}
                            {isMe && (
                              <div className={`absolute -top-3 ${isMe ? "right-0" : "left-0"} hidden group-hover:flex items-center gap-1 z-10`}>
                                <button
                                  onClick={() => { setEditingMsgId(msg.id); setEditText(msg.message); setDeletingMsgId(null); }}
                                  title="Edit message"
                                  className="flex items-center justify-center h-6 w-6 rounded-lg bg-[#1A1B25] border border-gold/30 text-gold hover:bg-gold/20 transition-all shadow-lg"
                                >
                                  <Pencil className="h-3 w-3" />
                                </button>
                                <button
                                  onClick={() => { setDeletingMsgId(msg.id); setEditingMsgId(null); }}
                                  title="Delete message"
                                  className="flex items-center justify-center h-6 w-6 rounded-lg bg-[#1A1B25] border border-red-500/30 text-red-400 hover:bg-red-500/20 transition-all shadow-lg"
                                >
                                  <Trash2 className="h-3 w-3" />
                                </button>
                              </div>
                            )}
                            <div
                              className={`p-2.5 sm:p-3 rounded-2xl text-xs leading-relaxed max-w-sm sm:max-w-md break-words shadow-lg ${
                                isMe
                                  ? "bg-[#005c4b] text-emerald-50 font-medium rounded-tr-none border border-[#007a63]/50 shadow-[0_2px_8px_rgba(0,92,75,0.4)]"
                                  : "bg-[#202c33] border border-white/10 text-zinc-100 rounded-tl-none shadow-md"
                              }`}
                            >
                              {msg.attachmentUrl && (
                                <div className="mb-2 max-w-[300px] relative group">
                                  {msg.isPending && !msg.attachmentUrl.startsWith("blob:") ? (
                                    <div className="flex items-center gap-2 p-2.5 bg-black/40 rounded-xl border border-gold/30">
                                      <Loader2 className="h-4 w-4 animate-spin text-gold shrink-0" />
                                      <span className="truncate flex-1 font-medium text-xs text-gold">
                                        Uploading media ({msg.attachmentUrl})…
                                      </span>
                                    </div>
                                  ) : ((msg.attachmentMime?.startsWith("image/") || msg.attachmentUrl.match(/\.(jpeg|jpg|gif|png|webp|heic|heif)$/i) != null) || msg.attachmentUrl.startsWith("blob:")) ? (
                                    <>
                                      <img src={msg.attachmentUrl} alt="attachment" loading="lazy" decoding="async" className="rounded-xl w-full h-auto max-h-[300px] object-cover border border-white/20 shadow-md" />
                                      {!msg.isPending && (
                                        <a href={msg.attachmentUrl} download target="_blank" rel="noopener noreferrer" className="absolute top-2 right-2 p-1.5 bg-black/60 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/80">
                                          <Download className="h-4 w-4" />
                                        </a>
                                      )}
                                    </>
                                  ) : (msg.attachmentMime?.startsWith("video/") || msg.attachmentMime === "audio/3gpp" || msg.attachmentMime === "audio/mp4" || msg.attachmentUrl.match(/\.(mp4|webm|ogg|mov|avi|mkv|3gp|m4v|m4a)$/i) != null) ? (
                                    <>
                                      <video src={msg.attachmentUrl} controls preload="metadata" className="rounded-xl w-full h-auto max-h-[300px] border border-white/20 shadow-md" />
                                      <a href={msg.attachmentUrl} download target="_blank" rel="noopener noreferrer" className="absolute top-2 right-2 p-1.5 bg-black/60 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/80 z-10">
                                        <Download className="h-4 w-4" />
                                      </a>
                                    </>
                                  ) : (
                                    <div className="flex items-center gap-2 p-2.5 bg-black/30 rounded-xl border border-white/20">
                                      <FileText className="h-5 w-5 text-gold shrink-0" />
                                      <span className="truncate flex-1 font-medium text-xs">
                                        {msg.attachmentName || decodeURIComponent(msg.attachmentUrl.split("/").pop() || "Document File")}
                                      </span>
                                      <a
                                        href={msg.attachmentUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-1 text-white/70 hover:text-gold transition-colors"
                                        title="Preview Document"
                                      >
                                        <ExternalLink className="h-4 w-4" />
                                      </a>
                                      <a
                                        href={msg.attachmentUrl}
                                        download
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-1 text-white/70 hover:text-gold transition-colors"
                                        title="Download Document"
                                      >
                                        <Download className="h-4 w-4" />
                                      </a>
                                    </div>
                                  )}
                                </div>
                              )}
                              {(() => {
                                let callLogData: any = null;
                                if (msg.message && msg.message.startsWith("{") && msg.message.includes('"call_log"')) {
                                  try {
                                    callLogData = JSON.parse(msg.message);
                                  } catch (e) {}
                                }

                                if (callLogData && callLogData.type === "call_log") {
                                  const isVideo = callLogData.is_video;
                                  const status = callLogData.status;
                                  const isMissed = status === "missed" || callLogData.end_reason === "no_answer";
                                  const isDeclined = status === "declined";

                                  let icon = <PhoneOutgoing className="h-4 w-4 text-emerald-400 shrink-0" />;
                                  let subtitle = "Outgoing call";
                                  if (isMissed) {
                                    icon = <PhoneMissed className="h-4 w-4 text-rose-400 shrink-0" />;
                                    subtitle = isMe ? "Unanswered call" : "Missed call";
                                  } else if (isDeclined) {
                                    icon = <PhoneOff className="h-4 w-4 text-rose-400 shrink-0" />;
                                    subtitle = "Declined call";
                                  } else if (!isMe) {
                                    icon = <PhoneIncoming className="h-4 w-4 text-emerald-400 shrink-0" />;
                                    subtitle = "Incoming call";
                                  }

                                  const durationText = callLogData.duration_seconds > 0
                                    ? `${Math.floor(callLogData.duration_seconds / 60)}m ${callLogData.duration_seconds % 60}s`
                                    : subtitle;

                                  return (
                                    <div className="flex flex-col gap-2 min-w-[210px] max-w-[260px]">
                                      <div className="flex items-center gap-2.5">
                                        <div className={`p-2 rounded-full shrink-0 ${isMissed || isDeclined ? "bg-rose-500/20 text-rose-400 border border-rose-500/30" : "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"}`}>
                                          {isVideo ? <Video className="h-4 w-4" /> : <Phone className="h-4 w-4" />}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                          <div className="font-bold text-xs flex items-center gap-1.5 text-white">
                                            {icon}
                                            <span className="truncate">{callLogData.title || (isVideo ? "Video call" : "Voice call")}</span>
                                          </div>
                                          <div className="text-[10px] text-zinc-300 font-mono mt-0.5">
                                            {durationText}
                                          </div>
                                        </div>
                                      </div>

                                      {!isMe && (
                                        <button
                                          type="button"
                                          onClick={() => handleStartChatCall(isVideo)}
                                          className="w-full mt-0.5 py-1 px-2.5 rounded-lg bg-black/40 hover:bg-black/60 border border-white/20 text-amber-300 text-[10px] font-bold flex items-center justify-center gap-1.5 transition-all"
                                        >
                                          {isVideo ? <Video className="h-3 w-3" /> : <Phone className="h-3 w-3" />}
                                          <span>Call Back</span>
                                        </button>
                                      )}
                                    </div>
                                  );
                                }

                                return msg.message ? <div>{msg.message}</div> : null;
                              })()}
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Message Composer Footer */}
          <form onSubmit={handleSendMessage} className="p-3 sm:p-4 border-t border-gold/15 bg-[#0F1017] shrink-0 flex flex-col gap-2">
            {attachment && (
              <div className="flex items-center justify-between p-2 bg-white/5 rounded-xl border border-white/10 w-fit">
                <div className="flex items-center gap-2">
                  {attachment.type?.includes("image") ? (
                    <FileImage className="h-4 w-4 text-gold" />
                  ) : (
                    <FileText className="h-4 w-4 text-gold" />
                  )}
                  <span className="text-xs text-foreground max-w-[200px] truncate">{attachment.name}</span>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setAttachment(null);
                    if (fileInputRef.current) fileInputRef.current.value = "";
                  }}
                  className="ml-4 text-muted-foreground hover:text-red-500"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            )}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="h-10 w-10 shrink-0 flex items-center justify-center rounded-xl border border-border bg-input/40 text-muted-foreground hover:bg-white/10 hover:text-gold transition-colors"
                title="Attach file"
              >
                <Paperclip className="h-4 w-4" />
              </button>
              <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                accept="image/*,video/*,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.zip,.rar,.rtf,.odt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"
                onChange={(e) => {
                  if (e.target.files && e.target.files[0]) {
                    setAttachment(e.target.files[0]);
                  }
                }}
              />
              <input
                value={inputMessage}
                onChange={(e) => setInputMessage(e.target.value)}
                placeholder={`Message ${activeType === "channel" ? `#${activeChannel?.display_name}` : activeMember?.name}…`}
                className="flex-1 rounded-xl border border-border bg-input/40 py-2.5 px-4 text-xs outline-none focus:border-gold/50 text-foreground"
              />
              <button
                type="submit"
                disabled={sending || (!inputMessage.trim() && !attachment)}
                className="flex items-center justify-center h-10 px-4 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] shrink-0 disabled:opacity-50 transition-all"
              >
                {sending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Send className="h-4 w-4" />
                )}
              </button>
            </div>
          </form>

        </div>
      </GlassCard>

      {/* Ringing overlay — shown BEFORE recipient accepts */}
      {ringingCall && (
        <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/80 backdrop-blur-2xl animate-in fade-in duration-200">
          <div className="flex flex-col items-center gap-6 rounded-3xl border border-gold/40 bg-[#0F1017] p-10 shadow-2xl text-center">
            <div className="relative flex h-20 w-20 items-center justify-center rounded-full bg-gold/20 border-2 border-gold/50">
              {ringingCall.isVideo
                ? <Video className="h-9 w-9 text-gold animate-pulse" />
                : <Phone className="h-9 w-9 text-gold animate-pulse" />}
              <span className="absolute inset-0 rounded-full animate-ping bg-gold/20 border border-gold/30" />
            </div>
            <div>
              <h3 className="text-xl font-bold text-foreground">
                Calling {activeMember?.name || "Team Member"}...
              </h3>
              <p className="text-sm text-muted-foreground mt-1">
                {ringingCall.isVideo ? "Video" : "Audio"} call · Waiting for answer
              </p>
            </div>
            <button
              onClick={handleCancelRinging}
              className="flex items-center gap-2 rounded-2xl bg-destructive/20 border border-destructive/40 px-6 py-3 text-sm font-semibold text-destructive hover:bg-destructive/30 transition-all"
            >
              <PhoneOff className="h-5 w-5" /> Cancel Call
            </button>
          </div>
        </div>
      )}

      {/* Active call modal — only opened once recipient accepts */}
      <VideoCallModal
        open={Boolean(activeChatCall)}
        onClose={() => setActiveChatCall(null)}
        roomCode={activeChatCall?.roomId}
        leadId=""
        leadName={activeMember?.name || "Team Member"}
        callTitle={`Team ${activeChatCall?.isVideo ? "Video" : "Audio"} Call`}
        zegoConfig={{
          appId: activeChatCall?.zegoAppId,
          appSign: activeChatCall?.zegoAppSign,
          serverSecret: activeChatCall?.zegoServerSecret,
          isVideo: activeChatCall?.isVideo,
        }}
        onCallEnded={() => {
          const callToEnd = activeChatCall;
          setActiveChatCall(null);
          if (callToEnd) {
            chatCallAction(callToEnd.id, "end").catch(() => {});
          }
        }}
      />

      {/* Create Group Chat Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-md rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5">
            <button
              onClick={() => setIsCreateModalOpen(false)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <Users className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-lg font-semibold text-foreground">Create New Group Chat</h2>
                <p className="text-xs text-muted-foreground">Add a team channel like in WhatsApp or Slack</p>
              </div>
            </div>

            <form onSubmit={handleCreateGroupSubmit} className="space-y-4 text-xs">
              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Group / Channel Name *</label>
                <input
                  required
                  value={newGroupName}
                  onChange={(e) => setNewGroupName(e.target.value)}
                  placeholder="e.g. Dispatch Alpha, London Relocations, Surveyors Guild"
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>

              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Group Description (Optional)</label>
                <input
                  value={newGroupDesc}
                  onChange={(e) => setNewGroupDesc(e.target.value)}
                  placeholder="What is this group chat for?"
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Select Members (Optional)</label>
                  <div className="max-h-[120px] overflow-y-auto space-y-1 p-2 rounded-xl border border-border bg-input/40">
                    {members.map((member) => (
                      <label key={member.id} className="flex items-center gap-2 text-xs p-1 hover:bg-white/5 rounded cursor-pointer">
                        <input
                          type="checkbox"
                          checked={newGroupMembers.includes(member.id)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setNewGroupMembers([...newGroupMembers, member.id]);
                            } else {
                              setNewGroupMembers(newGroupMembers.filter((id) => id !== member.id));
                            }
                          }}
                          className="rounded border-border bg-black/50 accent-gold focus:ring-0 w-3.5 h-3.5"
                        />
                        <span className="text-foreground">{member.name}</span>
                        <span className="text-muted-foreground text-[10px] uppercase ml-auto">{member.role}</span>
                      </label>
                    ))}
                  </div>
                </div>

              <div className="flex items-center gap-2.5 pt-1">
                <input
                  type="checkbox"
                  id="isPrivate"
                  checked={isNewGroupPrivate}
                  onChange={(e) => setIsNewGroupPrivate(e.target.checked)}
                  className="h-4 w-4 rounded border-border accent-gold cursor-pointer"
                />
                <label htmlFor="isPrivate" className="text-xs text-foreground cursor-pointer select-none">
                  Private Group (Only Admins & Managers)
                </label>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setIsCreateModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creatingGroup || !newGroupName.trim()}
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50 transition-all"
                >
                  {creatingGroup ? "Creating…" : "Create Group Chat"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

