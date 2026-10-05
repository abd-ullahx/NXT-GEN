import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState, useMemo } from "react";
import {
  Mail,
  Inbox,
  Send,
  RefreshCw,
  Search,
  Plus,
  X,
  ArrowLeft,
  ExternalLink,
  CheckCircle2,
  Circle,
  ArrowUpRight,
  ArrowDownLeft,
  Link2,
  AlertTriangle,
  Sparkles,
  MailOpen,
  Unplug,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  getOutlookAuthUrl,
  getOutlookStatus,
  disconnectOutlook,
  fetchOutlookEmails,
  syncOutlookEmails,
  sendOutlookEmail,
} from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";

export const Route = createFileRoute("/app/outlook")({
  head: () => ({
    meta: [{ title: "Outlook Inbox — Next Gen Relocation CRM" }],
  }),
  component: OutlookPage,
});

/* ────────────────────────────────────── types ─── */

interface OutlookEmail {
  id: number;
  messageId: string;
  direction: "inbound" | "outbound";
  fromEmail: string;
  fromName: string | null;
  toEmail: string;
  subject: string | null;
  bodyPreview: string | null;
  bodyHtml: string | null;
  isRead: boolean;
  receivedAt: string;
  leadId: string | null;
  createdAt: string;
}

interface ConnectionStatus {
  connected: boolean;
  email: string | null;
  displayName: string | null;
}

/* ─────────────────────────────────── helpers ─── */

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "Just now";
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(dateStr).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
  });
}

function initials(name: string | null, email: string): string {
  if (name) {
    return name
      .split(" ")
      .map((w) => w[0])
      .join("")
      .slice(0, 2)
      .toUpperCase();
  }
  return email.slice(0, 2).toUpperCase();
}

/* ─────────────────────────────── main component ─── */

function OutlookPage() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<ConnectionStatus>({
    connected: false,
    email: null,
    displayName: null,
  });
  const [emails, setEmails] = useState<OutlookEmail[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [query, setQuery] = useState("");
  const debouncedQuery = useDebouncedValue(query, 300);
  const [filter, setFilter] = useState<"all" | "inbound" | "outbound" | "unread">("all");
  const [selectedEmail, setSelectedEmail] = useState<OutlookEmail | null>(null);
  const [composing, setComposing] = useState(false);
  const [composeData, setComposeData] = useState({
    to: "",
    subject: "",
    body: "",
  });
  const [sending, setSending] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  /* ── load status + emails ─── */
  const loadStatus = async () => {
    try {
      const s = await getOutlookStatus();
      setStatus(s);
    } catch {
      /* not connected */
    }
  };

  const loadEmails = async (targetPage = page, targetFilter = filter, targetQuery = debouncedQuery) => {
    setLoading(true);
    try {
      const res = await fetchOutlookEmails(targetPage, perPage, targetQuery, targetFilter);
      setEmails(res.data || []);
      setTotal(res.total || 0);
    } catch {
      /* ignore */
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStatus();
    loadEmails(1, filter, debouncedQuery);

    const params = new URLSearchParams(window.location.search);
    const errParam = params.get("error");
    if (errParam) {
      showToast(errParam);
      // Clean up URL without reload
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }, []);

  useEffect(() => {
    setPage(1);
    loadEmails(1, filter, debouncedQuery);
  }, [debouncedQuery, filter, perPage]);

  const handlePageChange = (newPage: number) => {
    setPage(newPage);
    loadEmails(newPage, filter, debouncedQuery);
  };

  /* ── actions ─── */
  const handleConnect = async () => {
    try {
      const data = await getOutlookAuthUrl();
      if (data.error) {
        showToast(data.error);
        return;
      }
      if (data.url) {
        window.location.href = data.url;
      }
    } catch (err: any) {
      showToast(err.message || "Failed to start Outlook connection");
    }
  };

  const handleDisconnect = async () => {
    await disconnectOutlook();
    setStatus({ connected: false, email: null, displayName: null });
    setEmails([]);
    showToast("Outlook disconnected");
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      const res = await syncOutlookEmails();
      showToast(`${res.synced} new emails synced`);
      loadEmails();
      // Invalidate leads, contacts, and dashboard queries so newly parsed leads immediately appear across all tabs
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["contacts"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    } catch {
      showToast("Sync failed — check connection");
    } finally {
      setSyncing(false);
    }
  };

  const handleSend = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    try {
      await sendOutlookEmail(composeData.to, composeData.subject, composeData.body);
      showToast("Email sent successfully");
      setComposing(false);
      setComposeData({ to: "", subject: "", body: "" });
      loadEmails();
    } catch (err: any) {
      showToast(err?.message || "Failed to send email");
    } finally {
      setSending(false);
    }
  };

  const showToast = (msg: string) => {
    setToast(msg);
    setTimeout(() => setToast(null), 3500);
  };

  /* ── filters ─── */
  const filterTabs: { key: typeof filter; label: string; icon: any }[] = [
    { key: "all", label: "All Mail", icon: Mail },
    { key: "inbound", label: "Inbox", icon: Inbox },
    { key: "outbound", label: "Sent", icon: Send },
    { key: "unread", label: "Unread", icon: Circle },
  ];

  const unreadCount = useMemo(
    () => emails.filter((e) => !e.isRead && e.direction === "inbound").length,
    [emails]
  );

  /* ──────────────────────────────────── RENDER ─── */

  /* Not connected state */
  if (!status.connected && !loading) {
    return (
      <div className="space-y-6">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Mail className="h-3.5 w-3.5" /> Email
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">
            Microsoft Outlook
          </h1>
          <p className="text-sm text-muted-foreground">
            Connect your Outlook account to sync emails, auto-capture leads and
            send replies — all from within the CRM.
          </p>
        </div>

        <GlassCard className="mx-auto max-w-xl p-8 text-center">
          {/* Outlook icon */}
          <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-[#0F6CBD]/15 ring-1 ring-[#0F6CBD]/30">
            <svg viewBox="0 0 24 24" className="h-10 w-10 text-[#0F6CBD]" fill="currentColor">
              <path d="M21.17 2.06A1.5 1.5 0 0122.5 3.5v17a1.5 1.5 0 01-1.33 1.44l-.17.06H14v-2h6V4h-6V2h7.17zM13.5 2v20L2 19V5l11.5-3zM9.25 7.5c-2.21 0-4 2.01-4 4.5s1.79 4.5 4 4.5 4-2.01 4-4.5-1.79-4.5-4-4.5zm0 2c1.1 0 2 1.12 2 2.5s-.9 2.5-2 2.5-2-1.12-2-2.5.9-2.5 2-2.5zM14 8v2h4V8h-4zm0 4v2h4v-2h-4zm0 4v2h4v-2h-4z"/>
            </svg>
          </div>

          <h2 className="font-display text-2xl font-semibold">
            Connect Microsoft Outlook
          </h2>
          <p className="mt-2 text-sm text-muted-foreground">
            Sign in with your Microsoft account to sync your inbox. This uses
            the free Microsoft Graph API — no paid subscriptions required.
          </p>

          <div className="mt-6 space-y-3">
            <button
              onClick={handleConnect}
              className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#0F6CBD] to-[#0078D4] px-5 py-3 text-sm font-semibold text-white transition-all hover:shadow-lg hover:shadow-[#0F6CBD]/30"
            >
              <Mail className="h-4 w-4" /> Sign in with Microsoft
            </button>
          </div>

          <div className="mt-6 grid grid-cols-3 gap-3 text-left">
            {[
              {
                icon: Inbox,
                title: "Auto-Sync Inbox",
                desc: "Emails synced into CRM in real-time.",
              },
              {
                icon: Sparkles,
                title: "Auto-Create Leads",
                desc: "Unknown senders become leads instantly.",
              },
              {
                icon: Send,
                title: "Send from CRM",
                desc: "Reply and compose without leaving.",
              },
            ].map((f) => (
              <div
                key={f.title}
                className="rounded-xl border border-border/60 bg-card/30 p-3"
              >
                <f.icon className="mb-2 h-4 w-4 text-gold" />
                <div className="text-xs font-medium">{f.title}</div>
                <div className="mt-0.5 text-[10px] text-muted-foreground">
                  {f.desc}
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      </div>
    );
  }

  /* Connected state */
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Mail className="h-3.5 w-3.5" /> Outlook
            {status.connected && (
              <span className="ml-2 flex items-center gap-1 rounded-full bg-success/15 px-2 py-0.5 text-[10px] text-success border border-success/30">
                <CheckCircle2 className="h-3 w-3" /> Connected
              </span>
            )}
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Inbox</h1>
          <p className="text-sm text-muted-foreground">
            {status.email
              ? `Synced with ${status.email}`
              : "Manage your Outlook emails"}
            {total > 0 && ` · ${total} emails`}
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={handleSync}
            disabled={syncing}
            className="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2.5 text-sm transition-colors hover:border-gold/40 disabled:opacity-50"
          >
            <RefreshCw
              className={`h-4 w-4 ${syncing ? "animate-spin" : ""}`}
            />
            {syncing ? "Syncing…" : "Sync"}
          </button>
          <button
            onClick={handleDisconnect}
            className="flex items-center gap-2 rounded-xl border border-destructive/40 bg-destructive/10 px-3.5 py-2.5 text-sm text-destructive transition-all hover:bg-destructive/20"
            title="Disconnect Outlook account"
          >
            <Unplug className="h-4 w-4" /> Disconnect
          </button>
          <button
            onClick={() => setComposing(true)}
            className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]"
          >
            <Plus className="h-4 w-4" /> Compose
          </button>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="flex flex-wrap items-center gap-2">
        {filterTabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setFilter(t.key)}
            className={`flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs transition-all ${
              filter === t.key
                ? "border-gold/50 bg-gold/15 text-gold"
                : "border-border bg-card/40 text-muted-foreground hover:border-gold/30 hover:text-foreground"
            }`}
          >
            <t.icon className="h-3 w-3" />
            {t.label}
            {t.key === "unread" && unreadCount > 0 && (
              <span className="ml-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-gold text-[9px] font-bold text-primary-foreground px-1">
                {unreadCount}
              </span>
            )}
          </button>
        ))}
        <button
          onClick={handleDisconnect}
          className="ml-auto flex items-center gap-1.5 rounded-full border border-border bg-card/40 px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-destructive/40 hover:text-destructive"
        >
          <Unplug className="h-3 w-3" /> Disconnect
        </button>
      </div>

      {/* Email list + detail */}
      <div className="grid gap-6 lg:grid-cols-5">
        {/* Email list */}
        <GlassCard className="overflow-hidden lg:col-span-2">
          {/* Search */}
          <div className="border-b border-border/60 p-3">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search emails…"
                className="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
              />
            </div>
          </div>

          {/* List */}
          <div className="max-h-[600px] overflow-y-auto divide-y divide-border/40">
            {loading ? (
              <div className="px-5 py-12 text-center text-sm text-muted-foreground">
                Loading emails…
              </div>
            ) : emails.length === 0 ? (
              <div className="px-5 py-12 text-center text-sm text-muted-foreground">
                <MailOpen className="mx-auto mb-3 h-8 w-8 text-muted-foreground/50" />
                {status.connected
                  ? "No emails found. Try syncing."
                  : "Connect Outlook to see your emails."}
              </div>
            ) : (
              emails.map((email) => (
                <button
                  key={email.id}
                  onClick={() => setSelectedEmail(email)}
                  className={`w-full px-4 py-3 text-left transition-colors hover:bg-gold/[0.04] ${
                    selectedEmail?.id === email.id
                      ? "bg-gold/[0.08] border-l-2 border-l-gold"
                      : ""
                  } ${!email.isRead ? "bg-card/50" : ""}`}
                >
                  <div className="flex items-start gap-3">
                    {/* Avatar */}
                    <div
                      className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                        email.direction === "outbound"
                          ? "bg-chart-3/20 text-chart-3"
                          : "bg-gradient-to-br from-gold/30 to-transparent text-gold"
                      }`}
                    >
                      {initials(
                        email.direction === "outbound"
                          ? null
                          : email.fromName,
                        email.direction === "outbound"
                          ? email.toEmail
                          : email.fromEmail
                      )}
                    </div>

                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-1.5 truncate">
                          {!email.isRead && (
                            <span className="h-2 w-2 shrink-0 rounded-full bg-gold" />
                          )}
                          <span
                            className={`truncate text-sm ${
                              !email.isRead
                                ? "font-semibold text-foreground"
                                : "font-medium text-foreground/80"
                            }`}
                          >
                            {email.direction === "outbound"
                              ? `To: ${email.toEmail}`
                              : email.fromName || email.fromEmail}
                          </span>
                        </div>
                        <span className="shrink-0 text-[10px] text-muted-foreground">
                          {timeAgo(email.receivedAt)}
                        </span>
                      </div>

                      <div className="mt-0.5 truncate text-xs font-medium text-foreground/70">
                        {email.subject || "(No Subject)"}
                      </div>
                      <div className="mt-0.5 truncate text-[11px] text-muted-foreground">
                        {email.bodyPreview}
                      </div>

                      {/* Tags */}
                      <div className="mt-1.5 flex items-center gap-1.5">
                        {email.direction === "inbound" ? (
                          <span className="flex items-center gap-0.5 rounded-md border border-chart-3/30 bg-chart-3/10 px-1.5 py-0.5 text-[9px] text-chart-3">
                            <ArrowDownLeft className="h-2.5 w-2.5" /> Inbound
                          </span>
                        ) : (
                          <span className="flex items-center gap-0.5 rounded-md border border-gold/30 bg-gold/10 px-1.5 py-0.5 text-[9px] text-gold">
                            <ArrowUpRight className="h-2.5 w-2.5" /> {email.messageId?.startsWith("brevo-") ? "Sent (Brevo)" : "Sent (Outlook)"}
                          </span>
                        )}
                        {email.leadId && (
                          <span className="flex items-center gap-0.5 rounded-md border border-warning/30 bg-warning/10 px-1.5 py-0.5 text-[9px] text-warning">
                            <Link2 className="h-2.5 w-2.5" /> {email.leadId}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </button>
              ))
            )}
          </div>

          {/* Pagination Footer Bar */}
          {total > 0 && (
            <div className="flex items-center justify-between border-t border-border/60 bg-card/40 px-4 py-2 text-xs">
              <span className="text-muted-foreground">
                Showing {Math.min((page - 1) * perPage + 1, total)}–{Math.min(page * perPage, total)} of {total}
              </span>
              <div className="flex items-center gap-2">
                <select
                  value={perPage}
                  onChange={(e) => {
                    setPerPage(Number(e.target.value));
                    setPage(1);
                  }}
                  className="rounded-lg border border-border bg-input/40 px-2 py-1 text-[11px] text-foreground outline-none focus:border-gold/50"
                >
                  <option value={10} className="bg-card text-foreground">10 per page</option>
                  <option value={15} className="bg-card text-foreground">15 per page</option>
                  <option value={25} className="bg-card text-foreground">25 per page</option>
                  <option value={50} className="bg-card text-foreground">50 per page</option>
                </select>
                <div className="flex items-center gap-1">
                  <button
                    disabled={page <= 1 || loading}
                    onClick={() => handlePageChange(page - 1)}
                    className="rounded-lg border border-border bg-card p-1 text-muted-foreground hover:border-gold/40 hover:text-gold disabled:opacity-30"
                    title="Previous page"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" />
                  </button>
                  <span className="px-2 font-medium text-foreground text-[11px]">
                    Page {page} of {Math.ceil(total / perPage) || 1}
                  </span>
                  <button
                    disabled={page * perPage >= total || loading}
                    onClick={() => handlePageChange(page + 1)}
                    className="rounded-lg border border-border bg-card p-1 text-muted-foreground hover:border-gold/40 hover:text-gold disabled:opacity-30"
                    title="Next page"
                  >
                    <ChevronRight className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </div>
          )}
        </GlassCard>

        {/* Email detail */}
        <GlassCard className="lg:col-span-3 min-h-[500px]">
          {selectedEmail ? (
            <div className="flex h-full flex-col">
              {/* Detail header */}
              <div className="border-b border-border/60 p-5">
                <button
                  onClick={() => setSelectedEmail(null)}
                  className="mb-3 flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground lg:hidden"
                >
                  <ArrowLeft className="h-3.5 w-3.5" /> Back to list
                </button>
                <h2 className="font-display text-xl font-semibold leading-tight">
                  {selectedEmail.subject || "(No Subject)"}
                </h2>
                <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                  <div className="flex items-center gap-2">
                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-[10px] font-semibold text-gold">
                      {initials(selectedEmail.fromName, selectedEmail.fromEmail)}
                    </div>
                    <div>
                      <span className="font-medium text-foreground">
                        {selectedEmail.fromName || selectedEmail.fromEmail}
                      </span>
                      {selectedEmail.fromName && (
                        <span className="ml-1 text-xs text-muted-foreground">
                          &lt;{selectedEmail.fromEmail}&gt;
                        </span>
                      )}
                    </div>
                  </div>
                  <span className="text-xs">→</span>
                  <span className="text-xs">{selectedEmail.toEmail}</span>
                  <span className="ml-auto text-xs">
                    {new Date(selectedEmail.receivedAt).toLocaleString("en-GB", {
                      day: "numeric",
                      month: "short",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                  </span>
                </div>
                <div className="mt-2 flex items-center gap-2">
                  {selectedEmail.direction === "outbound" ? (
                    <span className="inline-flex items-center gap-1 rounded-md border border-gold/30 bg-gold/10 px-2 py-0.5 text-xs font-semibold text-gold">
                      <ArrowUpRight className="h-3 w-3" /> Sent via Brevo SMTP [{selectedEmail.fromEmail}]
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 rounded-md border border-chart-3/30 bg-chart-3/10 px-2 py-0.5 text-xs font-semibold text-chart-3">
                      <ArrowDownLeft className="h-3 w-3" /> Received via Outlook Graph API [{selectedEmail.toEmail}]
                    </span>
                  )}
                  {selectedEmail.leadId && (
                    <span className="inline-flex items-center gap-1 rounded-md border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs text-warning">
                      <Link2 className="h-3 w-3" /> Linked to lead{" "}
                      <strong>{selectedEmail.leadId}</strong>
                    </span>
                  )}
                </div>
              </div>

              {/* Email body */}
              <div className="flex-1 overflow-auto p-5">
                {selectedEmail.bodyHtml ? (
                  <div
                    className="prose prose-invert prose-sm max-w-none text-sm text-foreground/85 [&_a]:text-gold [&_a]:underline"
                    dangerouslySetInnerHTML={{
                      __html: selectedEmail.bodyHtml,
                    }}
                  />
                ) : (
                  <p className="whitespace-pre-wrap text-sm text-foreground/85">
                    {selectedEmail.bodyPreview}
                  </p>
                )}
              </div>

              {/* Quick reply */}
              <div className="border-t border-border/60 p-4">
                <button
                  onClick={() => {
                    setComposeData({
                      to:
                        selectedEmail.direction === "inbound"
                          ? selectedEmail.fromEmail
                          : selectedEmail.toEmail,
                      subject: `Re: ${selectedEmail.subject || ""}`,
                      body: "",
                    });
                    setComposing(true);
                  }}
                  className="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2 text-sm transition-colors hover:border-gold/40"
                >
                  <Send className="h-3.5 w-3.5" /> Reply
                </button>
              </div>
            </div>
          ) : (
            <div className="flex h-full flex-col items-center justify-center text-center p-8">
              <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gold/10">
                <Mail className="h-8 w-8 text-gold/60" />
              </div>
              <h3 className="font-display text-lg font-semibold text-foreground/60">
                Select an email
              </h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Click an email from the list to preview it here.
              </p>
            </div>
          )}
        </GlassCard>
      </div>

      {/* Compose Modal */}
      {composing && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
            <button
              onClick={() => setComposing(false)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>
            <h2 className="font-display text-2xl font-semibold mb-1">
              Compose Email
            </h2>
            <p className="text-xs text-muted-foreground mb-4">
              Send via your connected Outlook account ({status.email}).
            </p>
            <form onSubmit={handleSend} className="space-y-4">
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">
                  To
                </label>
                <input
                  required
                  type="email"
                  value={composeData.to}
                  onChange={(e) =>
                    setComposeData({ ...composeData, to: e.target.value })
                  }
                  placeholder="recipient@example.com"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">
                  Subject
                </label>
                <input
                  required
                  value={composeData.subject}
                  onChange={(e) =>
                    setComposeData({ ...composeData, subject: e.target.value })
                  }
                  placeholder="Re: Your removal enquiry"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">
                  Message
                </label>
                <textarea
                  required
                  rows={8}
                  value={composeData.body}
                  onChange={(e) =>
                    setComposeData({ ...composeData, body: e.target.value })
                  }
                  placeholder="Write your email…"
                  className="w-full resize-none rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setComposing(false)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={sending}
                  className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50"
                >
                  <Send className="h-3.5 w-3.5" />
                  {sending ? "Sending…" : "Send Email"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Toast */}
      {toast && (
        <div className="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-xl border border-gold/30 bg-card px-4 py-3 text-sm shadow-lg animate-in fade-in slide-in-from-bottom-4 duration-300">
          <CheckCircle2 className="h-4 w-4 text-gold" />
          {toast}
        </div>
      )}
    </div>
  );
}
