import { createFileRoute, Link } from "@tanstack/react-router";
import { useState, useMemo } from "react";
import { toast } from "sonner";
import {
  Calendar,
  Clock,
  MessageSquare,
  AlertTriangle,
  Check,
  ArrowLeft,
  Filter,
  User,
  Truck,
  Phone,
  Mail,
  RefreshCw,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import {
  useRescheduleRequestsQuery,
  useApproveRescheduleMutation,
  useRejectRescheduleMutation,
} from "@/lib/queries";

export const Route = createFileRoute("/app/job-reschedules")({
  head: () => ({ meta: [{ title: "Job Reschedule Requests — Next Gen CRM" }] }),
  component: JobReschedulesPage,
});

function JobReschedulesPage() {
  const [filterRole, setFilterRole] = useState<string>("all");
  const [approvingId, setApprovingId] = useState<string | null>(null);

  const { data: reschedData, isLoading, refetch } = useRescheduleRequestsQuery();
  const rescheduleRequests = reschedData?.requests || [];

  const approveReschedMutation = useApproveRescheduleMutation();
  const rejectReschedMutation = useRejectRescheduleMutation();

  const filteredRequests = useMemo(() => {
    if (filterRole === "client") {
      return rescheduleRequests.filter((r: any) => (r.requested_by || "").toLowerCase() === "client");
    }
    if (filterRole === "driver") {
      return rescheduleRequests.filter((r: any) => (r.requested_by || "").toLowerCase() === "driver");
    }
    return rescheduleRequests;
  }, [rescheduleRequests, filterRole]);

  const clientCount = useMemo(
    () => rescheduleRequests.filter((r: any) => (r.requested_by || "").toLowerCase() === "client").length,
    [rescheduleRequests]
  );

  const driverCount = useMemo(
    () => rescheduleRequests.filter((r: any) => (r.requested_by || "").toLowerCase() === "driver").length,
    [rescheduleRequests]
  );

  const handleApproveReschedule = async (req: any) => {
    setApprovingId(req.id);
    try {
      await approveReschedMutation.mutateAsync({
        id: req.id,
        date: req.proposed_date,
        time: req.proposed_time,
      });
      toast.success(`Reschedule Approved! Schedule updated to ${req.proposed_date} at ${req.proposed_time} everywhere.`);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Failed to approve reschedule request");
    } finally {
      setApprovingId(null);
    }
  };

  const handleRejectReschedule = async (req: any) => {
    try {
      await rejectReschedMutation.mutateAsync(req.id);
      toast.info("Reschedule request dismissed.");
    } catch (err) {
      toast.error("Failed to dismiss request");
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <Link
            to="/app/jobs"
            className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-gold transition-colors mb-2"
          >
            <ArrowLeft className="h-3.5 w-3.5" /> Back to Jobs Board
          </Link>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <AlertTriangle className="h-3.5 w-3.5 text-amber-400" /> Operations Center
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Job Reschedule Requests</h1>
          <p className="text-sm text-muted-foreground">
            Review date/time adjustment requests submitted by clients or drivers. Approving updates the schedule across calendar, leads, jobs, and email confirmations.
          </p>
        </div>

        <button
          onClick={() => refetch()}
          className="inline-flex items-center gap-2 rounded-xl border border-border bg-input/40 px-3.5 py-2 text-xs font-semibold text-foreground hover:border-gold/50 transition-colors"
        >
          <RefreshCw className="h-3.5 w-3.5 text-gold" /> Refresh Requests
        </button>
      </div>

      {/* Metrics */}
      <div className="grid gap-3 grid-cols-1 md:grid-cols-3">
        <GlassCard hover className="p-4">
          <div className="flex items-center justify-between text-[11px] text-muted-foreground uppercase tracking-wider">
            <span>Total Pending Requests</span>
            <AlertTriangle className="h-4 w-4 text-amber-400" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-amber-400">{rescheduleRequests.length}</div>
          <div className="mt-1 text-xs text-muted-foreground">Awaiting admin review</div>
        </GlassCard>

        <GlassCard hover className="p-4">
          <div className="flex items-center justify-between text-[11px] text-muted-foreground uppercase tracking-wider">
            <span>Requested by Clients</span>
            <User className="h-4 w-4 text-gold" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-gold">{clientCount}</div>
          <div className="mt-1 text-xs text-muted-foreground">Customer schedule preferences</div>
        </GlassCard>

        <GlassCard hover className="p-4">
          <div className="flex items-center justify-between text-[11px] text-muted-foreground uppercase tracking-wider">
            <span>Requested by Drivers</span>
            <Truck className="h-4 w-4 text-cyan-400" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-cyan-400">{driverCount}</div>
          <div className="mt-1 text-xs text-muted-foreground">Driver availability adjustments</div>
        </GlassCard>
      </div>

      {/* Filter bar */}
      <GlassCard className="p-4 flex items-center justify-between gap-4">
        <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
          <Filter className="h-4 w-4 text-gold" /> Filter Requests:
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setFilterRole("all")}
            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition-all ${
              filterRole === "all"
                ? "bg-gold text-primary-foreground shadow-sm"
                : "border border-border bg-input/20 text-muted-foreground hover:bg-card"
            }`}
          >
            All ({rescheduleRequests.length})
          </button>
          <button
            onClick={() => setFilterRole("client")}
            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition-all ${
              filterRole === "client"
                ? "bg-gold text-primary-foreground shadow-sm"
                : "border border-border bg-input/20 text-muted-foreground hover:bg-card"
            }`}
          >
            Clients ({clientCount})
          </button>
          <button
            onClick={() => setFilterRole("driver")}
            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition-all ${
              filterRole === "driver"
                ? "bg-gold text-primary-foreground shadow-sm"
                : "border border-border bg-input/20 text-muted-foreground hover:bg-card"
            }`}
          >
            Drivers ({driverCount})
          </button>
        </div>
      </GlassCard>

      {/* Requests List */}
      <div className="space-y-4">
        {filteredRequests.map((r: any) => (
          <GlassCard key={r.id} className="p-6 border-amber-500/30 space-y-4 hover:border-amber-500/60 transition-all">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border/50 pb-4">
              <div>
                <div className="flex items-center gap-2 flex-wrap">
                  <h3 className="font-display text-xl font-semibold text-foreground">{r.client_name}</h3>
                  <span className="text-xs font-mono text-muted-foreground bg-input/60 px-2 py-0.5 rounded-md border border-border/40">
                    Job #{r.job_id}
                  </span>
                  <span
                    className={`rounded-full px-3 py-0.5 text-xs font-bold border ${
                      (r.requested_by || "").toLowerCase() === "driver"
                        ? "bg-cyan-500/20 text-cyan-400 border-cyan-500/30"
                        : "bg-gold/20 text-gold border-gold/30"
                    }`}
                  >
                    Requested by {r.requested_by}
                  </span>
                </div>

                <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                  {r.client_email && (
                    <div className="flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5 text-gold/80" /> {r.client_email}
                    </div>
                  )}
                  {r.client_phone && (
                    <div className="flex items-center gap-1">
                      <Phone className="h-3.5 w-3.5 text-gold/80" /> {r.client_phone}
                    </div>
                  )}
                  <div className="flex items-center gap-1">
                    <Truck className="h-3.5 w-3.5 text-gold/80" /> Assigned Driver: <strong className="text-foreground">{r.driver_name}</strong>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-3 shrink-0">
                <button
                  onClick={() => handleRejectReschedule(r)}
                  disabled={approvingId === r.id}
                  className="rounded-xl border border-border bg-card/60 px-4 py-2.5 text-xs font-semibold text-muted-foreground hover:bg-card hover:text-foreground transition-colors"
                >
                  Dismiss Request
                </button>
                <button
                  onClick={() => handleApproveReschedule(r)}
                  disabled={approvingId === r.id}
                  className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg hover:shadow-emerald-500/20 hover:opacity-95 transition-all disabled:opacity-50"
                >
                  <Check className="h-4 w-4" />
                  {approvingId === r.id ? "Approving & Syncing..." : "Approve & Update Everywhere"}
                </button>
              </div>
            </div>

            {/* Schedule comparison grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="rounded-xl bg-card/40 border border-border/60 p-4 space-y-1">
                <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                  <Calendar className="h-3.5 w-3.5 text-muted-foreground" /> Current Scheduled Move
                </div>
                <div className="text-base font-bold text-foreground">{r.current_date}</div>
                <div className="text-xs text-muted-foreground flex items-center gap-1">
                  <Clock className="h-3 w-3" /> Arrival Window: {r.current_time}
                </div>
              </div>

              <div className="rounded-xl bg-amber-500/10 border border-amber-500/30 p-4 space-y-1">
                <div className="text-[11px] font-semibold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 text-amber-400" /> Proposed New Move Schedule
                </div>
                <div className="text-base font-bold text-amber-300">{r.proposed_date}</div>
                <div className="text-xs text-amber-200/80 flex items-center gap-1">
                  <Clock className="h-3 w-3" /> Requested Arrival Window: {r.proposed_time}
                </div>
              </div>
            </div>

            {/* Reason text area */}
            {r.reason && (
              <div className="rounded-xl bg-input/40 border border-border/50 p-3.5 text-xs text-foreground/90 flex items-start gap-2.5">
                <MessageSquare className="h-4 w-4 text-gold shrink-0 mt-0.5" />
                <div>
                  <strong className="text-gold font-semibold">Reason provided by {r.requested_by}:</strong>
                  <p className="mt-1 leading-relaxed text-foreground/90">{r.reason}</p>
                </div>
              </div>
            )}
          </GlassCard>
        ))}

        {filteredRequests.length === 0 && (
          <GlassCard className="p-12 text-center space-y-3">
            <Check className="h-10 w-10 text-emerald-400 mx-auto" />
            <h3 className="font-display text-xl font-semibold">No Pending Reschedule Requests</h3>
            <p className="text-xs text-muted-foreground max-w-sm mx-auto">
              All client &amp; driver schedule change requests have been processed and approved.
            </p>
          </GlassCard>
        )}
      </div>
    </div>
  );
}
