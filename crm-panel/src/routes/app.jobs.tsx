import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { Truck, Search, Filter, User, CheckCircle2, XCircle, RotateCcw, X, AlertTriangle, ShieldCheck, Clock, Check, Eye, RefreshCcw, Plus } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchJobs, assignJobDriver, updateJobStatus } from "@/lib/api";

export const Route = createFileRoute("/app/jobs")({
  head: () => ({ meta: [{ title: "Jobs — Next Gen CRM" }] }),
  validateSearch: (search: Record<string, unknown>) => ({
    search: search.search as string | undefined,
    view: search.view as string | undefined,
    highlight: search.highlight as string | undefined,
  }),
  component: JobsPage,
});

interface Job {
  id: string;
  title: string;
  status: "booked" | "completed_won" | "not_completed";
  date?: string;
  time?: string;
  leadId?: string;
  leadMoveDate?: string;
  driver?: string;
  vehicle?: string;
  location?: string;
  notes?: string;
  client_status?: string;
  driver_status?: string;
  is_confirmed?: boolean;
}

const statusMeta: Record<string, { label: string; style: string }> = {
  booked: { label: "Booked", style: "bg-gold/15 text-gold border-gold/30" },
  completed_won: { label: "Completed — Won", style: "bg-success/15 text-success border-success/30" },
  not_completed: { label: "Not Completed", style: "bg-destructive/15 text-destructive border-destructive/30" },
};

function JobStatusBadge({ job }: { job: Job }) {
  const isRescheduleRequested =
    job.client_status === "reschedule_requested" || job.driver_status === "reschedule_requested";

  if (job.status === "completed_won") {
    return (
      <div className="space-y-1.5">
        <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border bg-emerald-500/15 text-emerald-400 border-emerald-500/30 shadow-[0_0_12px_rgba(16,185,129,0.15)]">
          <CheckCircle2 className="h-3.5 w-3.5" /> Completed — Won
        </span>
      </div>
    );
  }

  if (job.status === "not_completed") {
    return (
      <div className="space-y-1.5">
        <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border bg-rose-500/15 text-rose-400 border-rose-500/30">
          <XCircle className="h-3.5 w-3.5" /> Not Completed
        </span>
      </div>
    );
  }

  return (
    <div className="space-y-2 min-w-[160px]">
      {/* Primary Status Pill */}
      {job.is_confirmed ? (
        <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border bg-gradient-to-r from-emerald-500/20 to-teal-500/20 text-emerald-300 border-emerald-500/40 shadow-[0_0_12px_rgba(16,185,129,0.2)]">
          <ShieldCheck className="h-3.5 w-3.5 text-emerald-400" /> Booked &amp; Confirmed
        </span>
      ) : isRescheduleRequested ? (
        <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border bg-amber-500/20 text-amber-300 border-amber-500/40 animate-pulse shadow-[0_0_10px_rgba(245,158,11,0.2)]">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-400" /> Reschedule Requested
        </span>
      ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border bg-amber-500/10 text-amber-300/90 border-amber-500/30">
          <Clock className="h-3.5 w-3.5 text-amber-400" /> Awaiting Confirmations
        </span>
      )}

      {/* Micro Party Badges */}
      <div className="flex flex-col gap-1 text-[11px]">
        {/* Client Status */}
        <div className="flex items-center justify-between gap-2 rounded-lg bg-input/40 px-2.5 py-1 border border-border/50">
          <span className="text-muted-foreground flex items-center gap-1 font-medium">
            <User className="h-3 w-3 text-gold" /> Client:
          </span>
          {job.client_status === "confirmed" ? (
            <span className="inline-flex items-center gap-1 text-emerald-400 font-bold">
              <Check className="h-3 w-3" /> Confirmed
            </span>
          ) : job.client_status === "reschedule_requested" ? (
            <span className="inline-flex items-center gap-1 text-amber-400 font-bold">
              <AlertTriangle className="h-3 w-3" /> Reschedule Req.
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 text-amber-300/80 font-medium">
              <Clock className="h-3 w-3" /> Pending
            </span>
          )}
        </div>

        {/* Driver Status */}
        <div className="flex items-center justify-between gap-2 rounded-lg bg-input/40 px-2.5 py-1 border border-border/50">
          <span className="text-muted-foreground flex items-center gap-1 font-medium">
            <Truck className="h-3 w-3 text-cyan-400" /> Driver:
          </span>
          {job.driver_status === "confirmed" ? (
            <span className="inline-flex items-center gap-1 text-emerald-400 font-bold">
              <Check className="h-3 w-3" /> Confirmed
            </span>
          ) : job.driver_status === "reschedule_requested" ? (
            <span className="inline-flex items-center gap-1 text-amber-400 font-bold">
              <AlertTriangle className="h-3 w-3" /> Reschedule Req.
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 text-amber-300/80 font-medium">
              <Clock className="h-3 w-3" /> Pending
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

import {
  useJobsQuery,
  useAssignJobDriverMutation,
  useUpdateJobStatusMutation,
  useRescheduleRequestsQuery,
  useAssignableDriversQuery,
} from "@/lib/queries";

function JobsPage() {
  useAssignableDriversQuery(); // Pre-warm cache so AssignDriverModal has 0ms load delay
  const searchParams = Route.useSearch();
  const [searchQuery, setSearchQuery] = useState(searchParams.search || "");
  const [highlightJobId, setHighlightJobId] = useState<string | null>(null);

  const debouncedSearch = useDebouncedValue(searchQuery, 300);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  
  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 50;
  const [assignJob, setAssignJob] = useState<Job | null>(null);
  const [viewProofsJob, setViewProofsJob] = useState<any | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);

  const { data: rawJobs = [], isLoading: loading, refetch } = useJobsQuery(statusFilter);
  const jobs: Job[] = useMemo(() => (Array.isArray(rawJobs) ? rawJobs : []), [rawJobs]);

  useEffect(() => {
    if (searchParams.highlight) {
      setHighlightJobId(searchParams.highlight);
      setTimeout(() => {
        const el = document.querySelector(`[data-job-id="${searchParams.highlight}"]`) || document.querySelector(`[data-lead-id="${searchParams.highlight}"]`);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 200);

      const timer = setTimeout(() => {
        setHighlightJobId(null);
      }, 4000);
      return () => clearTimeout(timer);
    }
  }, [searchParams.highlight, jobs.length]);
  const handleSync = async () => {
    setSyncing(true);
    try {
      await refetch();
      toast.success("Jobs synced successfully");
    } catch { toast.error("Sync failed"); }
    finally { setSyncing(false); }
  };

  const assignDriverMutation = useAssignJobDriverMutation();
  const updateStatusMutation = useUpdateJobStatusMutation();

  const { data: reschedData } = useRescheduleRequestsQuery();
  const rescheduleRequests = reschedData?.requests || [];

  const filteredJobs = useMemo(() => {
    const q = debouncedSearch.toLowerCase();
    return jobs.filter(
      (j) =>
        (j.title || "").toLowerCase().includes(q) ||
        (j.driver || "").toLowerCase().includes(q) ||
        (j.location || "").toLowerCase().includes(q) ||
        (j.id || "").toLowerCase().includes(q) ||
        (j.leadId || "").toLowerCase().includes(q),
    );
  }, [jobs, debouncedSearch]);

  // Reset page to 1 when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [debouncedSearch]);

  const totalPages = Math.ceil(filteredJobs.length / itemsPerPage);
  const paginatedJobs = filteredJobs.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  const counts = useMemo(
    () => ({
      booked: jobs.filter((j) => j.status === "booked" || j.status === "assigned" || j.status === "in_progress").length,
      won: jobs.filter((j) => j.status === "completed_won").length,
      notCompleted: jobs.filter((j) => j.status === "not_completed").length,
    }),
    [jobs],
  );

  const handleStatus = async (job: Job, status: Job["status"]) => {
    setBusyId(job.id);
    try {
      await updateStatusMutation.mutateAsync({ id: job.id, status });
    } catch (err) {
      console.error("Failed to update job status:", err);
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Truck className="h-3.5 w-3.5" /> Operations
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Jobs</h1>
          <p className="text-sm text-muted-foreground">
            Booked moves — assign a driver, review schedule adjustments, mark completed &amp; won, or reassign a driver.
          </p>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-3">
          <button
            onClick={handleSync}
            disabled={syncing}
            className="inline-flex items-center gap-2 rounded-xl border border-border bg-input/40 px-4 py-2.5 text-xs font-semibold text-foreground hover:bg-input/70 transition-all disabled:opacity-50"
          >
            <RefreshCcw className={`h-4 w-4 ${syncing ? "animate-spin" : ""}`} /> Sync
          </button>
          
          {/* Reschedule Requests Page Button */}
          <Link
            to="/app/job-reschedules"
            className="inline-flex items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-xs font-bold text-amber-300 hover:bg-amber-500/20 hover:border-amber-500/70 transition-all shadow-md"
          >
            <AlertTriangle className="h-4 w-4 text-amber-400 animate-pulse" />
            <span>Reschedule Requests</span>
            {rescheduleRequests.length > 0 ? (
              <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 text-black text-[11px] font-extrabold px-1.5 shadow-sm">
                {rescheduleRequests.length}
              </span>
            ) : (
              <span className="text-xs font-normal text-muted-foreground">(0)</span>
            )}
          </Link>
        </div>
      </div>

      {/* Metrics */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-3">
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Booked</span>
            <Truck className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-gold">{counts.booked}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Awaiting or in progress</div>
        </GlassCard>
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Completed — Won</span>
            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-success">{counts.won}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Successfully delivered</div>
        </GlassCard>
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Not Completed</span>
            <XCircle className="h-3.5 w-3.5 text-destructive" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-destructive">{counts.notCompleted}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Needs driver reassignment</div>
        </GlassCard>
      </div>

      {/* Search & Filter */}
      <GlassCard className="p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search jobs by title, driver, or location..."
            className="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
          />
        </div>
        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-muted-foreground" />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
          >
            <option value="all" className="bg-card">All Statuses</option>
            <option value="booked" className="bg-card">Booked</option>
            <option value="completed_won" className="bg-card">Completed — Won</option>
            <option value="not_completed" className="bg-card">Not Completed</option>
          </select>
        </div>
      </GlassCard>

      {/* Jobs table */}
      <GlassCard className="overflow-hidden">
        <div className="border-b border-border/60 p-5 flex items-center justify-between">
          <h2 className="font-display text-xl font-semibold">Jobs Board</h2>
          <span className="text-xs text-muted-foreground">{filteredJobs.length} jobs listed</span>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
              <tr>
                <th className="px-5 py-3">Job</th>
                <th className="px-5 py-3">Date</th>
                <th className="px-5 py-3">Driver / Vehicle</th>
                <th className="px-5 py-3">Location</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/40">
              {paginatedJobs.map((j) => {
                const isHighlighted = highlightJobId && (j.id === highlightJobId || j.leadId === highlightJobId);
                return (
                <tr 
                  key={j.id} 
                  data-job-id={j.id}
                  data-lead-id={j.leadId}
                  className={`transition-all duration-1000 ${
                    isHighlighted 
                      ? "bg-gold/20 shadow-[inset_0_0_0_2px_rgba(201,168,76,1)] z-10 relative" 
                      : "hover:bg-sidebar-accent/50"
                  }`}
                >
                  <td className="px-5 py-4">
                    <div className="font-medium text-foreground">{j.title}</div>
                    <div className="text-xs text-muted-foreground font-mono">{j.id}</div>
                  </td>
                  <td className="px-5 py-4 text-xs">{j.date || "—"}{j.time ? ` · ${j.time}` : ""}</td>
                  <td className="px-5 py-4 text-xs">
                    <div className="font-medium">{j.driver && j.driver !== "Unassigned" && j.driver !== "Unassigned Driver" ? j.driver : <span className="text-warning">Unassigned Driver</span>}</div>
                    <div className="text-muted-foreground">{j.vehicle || "—"}</div>
                  </td>
                  <td className="px-5 py-4 text-xs">{j.location || "—"}</td>
                  <td className="px-5 py-4">
                    <JobStatusBadge job={j} />
                  </td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => setViewProofsJob(j)}
                        className="inline-flex items-center gap-1 rounded-lg border border-cyan-500/40 bg-cyan-500/15 px-2.5 py-1.5 text-xs font-semibold text-cyan-300 hover:bg-cyan-500/25 transition-colors shadow-sm"
                        title="View Driver Proof Photos & Mileage Log"
                      >
                        <Eye className="h-3.5 w-3.5" /> Inspection Proofs
                      </button>
                      {["booked", "assigned", "in_progress"].includes(j.status) && (
                        <>
                          <button
                            onClick={() => setAssignJob(j)}
                            className="inline-flex items-center gap-1 rounded-lg border border-gold/40 bg-gold/15 px-2.5 py-1.5 text-xs font-semibold text-gold hover:bg-gold/25 transition-colors"
                          >
                            <User className="h-3.5 w-3.5" /> {j.driver && j.driver !== "Unassigned" && j.driver !== "Unassigned Driver" ? "Change Driver" : "Assign Driver"}
                          </button>
                        
                        </>
                      )}
                      {j.status === "not_completed" && (
                        <button
                          onClick={() => setAssignJob(j)}
                          className="inline-flex items-center gap-1 rounded-lg border border-gold/40 bg-gold/15 px-2.5 py-1.5 text-xs font-semibold text-gold hover:bg-gold/25 transition-colors"
                        >
                          <RotateCcw className="h-3.5 w-3.5" /> Reassign Driver
                        </button>
                      )}
                      {j.status === "completed_won" && (
                        <span className="text-xs text-success font-bold">Delivered ✓</span>
                      )}
                    </div>
                  </td>
                </tr>
                );
              })}
              {paginatedJobs.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-12 text-center text-sm text-muted-foreground">
                    {loading ? "Loading jobs…" : "No jobs yet. Jobs appear here once a move is booked."}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        
        {/* Pagination Controls */}
        {totalPages > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-white/5">
            <div className="text-sm text-white/50">
              Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredJobs.length)} of {filteredJobs.length} jobs
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="h-8 border-white/10 bg-white/5 hover:bg-white/10"
              >
                Previous
              </Button>
              <div className="text-sm text-white/70 px-4">
                Page {currentPage} of {totalPages}
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="h-8 border-white/10 bg-white/5 hover:bg-white/10"
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </GlassCard>

      {/* Assign / reassign driver modal */}
      {assignJob && (
        <AssignDriverModal
          job={assignJob}
          onClose={() => setAssignJob(null)}
          onSaved={() => setAssignJob(null)}
        />
      )}

      {/* Driver Inspection Proofs Modal */}
      {viewProofsJob && (
        <DriverProofsModal
          job={viewProofsJob}
          onClose={() => setViewProofsJob(null)}
        />
      )}

    </div>
  );
}

function AssignDriverModal({
  job,
  onClose,
  onSaved,
}: {
  job: Job;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [assignmentType, setAssignmentType] = useState<"approval" | "direct">("approval");
  const [driver, setDriver] = useState(job.driver && job.driver !== "Unassigned" ? job.driver : "");
  const [vehicle, setVehicle] = useState(job.vehicle || "");
  const isUnassigned = !job.driver || job.driver === "Unassigned" || job.driver === "Unassigned Driver";
  const defaultDate = isUnassigned 
    ? (job.leadMoveDate || job.date || new Date().toISOString().split("T")[0])
    : (job.date || job.leadMoveDate || new Date().toISOString().split("T")[0]);
  const [eventDate, setEventDate] = useState(defaultDate);
  const [eventTime, setEventTime] = useState(job.time || "09:00 AM");
  const [saving, setSaving] = useState(false);

  const { data: teamMembers = [], isLoading: loadingMembers } = useAssignableDriversQuery();

  const assignDriverMutation = useAssignJobDriverMutation();

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await assignDriverMutation.mutateAsync({
        id: job.id,
        driver,
        vehicle: vehicle || undefined,
        event_date: eventDate,
        event_time: eventTime,
        assignment_type: assignmentType,
      });
      toast.success(
        assignmentType === "direct"
          ? "Driver directly assigned! Schedule confirmed without reschedule workflow."
          : "Driver assigned & schedule approval notification sent!"
      );
      onSaved();
    } catch (err) {
      console.error("Failed to assign driver:", err);
      toast.error(err instanceof Error ? err.message : "Failed to assign driver");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
      <div className="glass-card w-full max-w-md rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
        <button onClick={onClose} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
          <X className="h-5 w-5" />
        </button>
        <h2 className="font-display text-2xl font-semibold mb-1">
          {job.status === "not_completed" ? "Reassign Driver" : "Assign Driver & Schedule"}
        </h2>
        <p className="text-xs text-muted-foreground mb-4">{job.title}</p>
        <form onSubmit={save} className="space-y-4">
          {/* Assignment Mode Selector */}
          <div>
            <label className="mb-1.5 block text-xs font-semibold text-gold">Assignment Mode *</label>
            <div className="grid grid-cols-2 gap-2 p-1 rounded-xl bg-input/40 border border-border/60">
              <button
                type="button"
                onClick={() => setAssignmentType("approval")}
                className={`py-2 px-3 rounded-lg text-xs font-bold transition-all ${
                  assignmentType === "approval"
                    ? "bg-gold/20 text-gold border border-gold/40 shadow-sm"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                📩 Send Approval
                <span className="block text-[10px] font-normal text-muted-foreground mt-0.5">Allows Reschedule</span>
              </button>
              <button
                type="button"
                onClick={() => setAssignmentType("direct")}
                className={`py-2 px-3 rounded-lg text-xs font-bold transition-all ${
                  assignmentType === "direct"
                    ? "bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                ⚡ Direct Assign
                <span className="block text-[10px] font-normal text-muted-foreground mt-0.5">Lock Date/Time</span>
              </button>
            </div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="block text-xs font-semibold text-muted-foreground">Select Team Member / Driver *</label>
              {loadingMembers && (
                <span className="text-[10px] text-gold animate-pulse font-medium">Loading members...</span>
              )}
            </div>
            <select
              required
              disabled={loadingMembers && teamMembers.length === 0}
              value={driver}
              onChange={(e) => setDriver(e.target.value)}
              className="w-full rounded-xl border border-border bg-[#181611] px-3 py-2.5 text-xs outline-none focus:border-gold/50 text-foreground cursor-pointer disabled:opacity-50"
            >
              <option value="" className="bg-[#181611] text-muted-foreground">
                {loadingMembers && teamMembers.length === 0
                  ? "-- Loading Registered Users... --"
                  : teamMembers.length === 0
                  ? "-- No registered users found --"
                  : "-- Choose Registered User --"}
              </option>
              {driver && !teamMembers.some((m) => m.name === driver) && (
                <option value={driver} className="bg-[#181611] text-foreground font-semibold">
                  {driver} (Assigned)
                </option>
              )}
              {teamMembers.map((m) => (
                <option key={m.id} value={m.name} className="bg-[#181611] text-foreground py-1">
                  {m.name} {m.email ? `(${m.email})` : ""} — Role: {String(m.role || "user").toUpperCase()}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                <span>Scheduled Date *</span>
                {job.leadMoveDate && (
                  <span className="text-[10px] font-bold text-gold bg-gold/10 border border-gold/30 px-1.5 py-0.5 rounded-full">
                    📅 Auto-filled from lead
                  </span>
                )}
              </label>
              <input
                type="date"
                required
                value={eventDate}
                onChange={(e) => setEventDate(e.target.value)}
                className="w-full rounded-xl border border-gold/40 bg-input/40 px-3 py-2 text-xs outline-none focus:border-gold/70 text-foreground"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Arrival Time *</label>
              <input
                type="text"
                required
                value={eventTime}
                onChange={(e) => setEventTime(e.target.value)}
                placeholder="e.g. 09:00 AM"
                className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-xs outline-none focus:border-gold/50 text-foreground"
              />
            </div>
          </div>

          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Vehicle Allocated</label>
            <input
              value={vehicle}
              onChange={(e) => setVehicle(e.target.value)}
              placeholder="e.g. Luton 7.5t · NG21"
              className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-xs outline-none focus:border-gold/50 text-foreground"
            />
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60">
              Cancel
            </button>
            <button type="submit" disabled={saving} className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50">
              {saving ? "Saving…" : assignmentType === "direct" ? "⚡ Direct Assign & Lock" : "📩 Assign & Send Approval"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function DriverProofsModal({ job, onClose }: { job: any; onClose: () => void }) {
  const proofs = job.proofs || {};
  const hasPreRide = proofs.selfie_url || proofs.start_meter_url || proofs.start_back_url;
  const hasPostRide = proofs.end_meter_url || proofs.end_back_url;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-md p-4 overflow-y-auto">
      <div className="glass-card w-full max-w-3xl rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150 my-8">
        <button onClick={onClose} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
          <X className="h-5 w-5" />
        </button>

        <div className="flex items-center gap-3 border-b border-border/60 pb-4 mb-4">
          <div className="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
            <Truck className="h-6 w-6" />
          </div>
          <div>
            <h2 className="font-display text-2xl font-semibold">Driver Inspection Proofs & Mileage</h2>
            <p className="text-xs text-muted-foreground">
              {job.title} ({job.id}) · Driver: <strong className="text-foreground">{job.driver || "Unassigned"}</strong>
            </p>
          </div>
        </div>

        {/* Telemetry Summary */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
          <div className="rounded-xl border border-border/60 bg-input/30 p-3">
            <span className="text-[11px] text-muted-foreground block">Start Odometer</span>
            <span className="font-mono text-base font-bold text-foreground">{job.start_odometer || 0} km</span>
          </div>
          <div className="rounded-xl border border-border/60 bg-input/30 p-3">
            <span className="text-[11px] text-muted-foreground block">End Odometer</span>
            <span className="font-mono text-base font-bold text-foreground">{job.end_odometer || 0} km</span>
          </div>
          <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3">
            <span className="text-[11px] text-emerald-300 block font-semibold">Total Distance</span>
            <span className="font-mono text-base font-bold text-emerald-400">{job.distance_km || 0} km</span>
          </div>
          <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3">
            <span className="text-[11px] text-amber-300 block font-semibold">Fuel Used</span>
            <span className="font-mono text-base font-bold text-amber-400">{job.fuel_liters || 0} L</span>
          </div>
        </div>

        {/* Pre-Ride Proofs (Stage 2) */}
        <div className="mb-6">
          <h3 className="text-sm font-semibold mb-3 flex items-center gap-2 text-cyan-300">
            <ShieldCheck className="h-4 w-4" /> 1. Pre-Ride Inspection Proofs (Start Ride)
          </h3>
          {hasPreRide ? (
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {/* Selfie */}
              <div className="rounded-xl border border-border/60 bg-input/30 p-2.5 space-y-2">
                <span className="text-xs font-semibold text-muted-foreground block">Driver Selfie</span>
                {proofs.selfie_url ? (
                  <a href={proofs.selfie_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border hover:border-cyan-500 transition-colors">
                    <img src={proofs.selfie_url} alt="Driver Selfie" className="h-36 w-full object-cover" />
                  </a>
                ) : (
                  <div className="h-36 flex items-center justify-center rounded-lg bg-card/40 text-xs text-muted-foreground">Not Uploaded</div>
                )}
              </div>

              {/* Start Meter */}
              <div className="rounded-xl border border-border/60 bg-input/30 p-2.5 space-y-2">
                <span className="text-xs font-semibold text-muted-foreground block">Start Odometer Meter</span>
                {proofs.start_meter_url ? (
                  <a href={proofs.start_meter_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border hover:border-cyan-500 transition-colors">
                    <img src={proofs.start_meter_url} alt="Start Meter" className="h-36 w-full object-cover" />
                  </a>
                ) : (
                  <div className="h-36 flex items-center justify-center rounded-lg bg-card/40 text-xs text-muted-foreground">Not Uploaded</div>
                )}
              </div>

              {/* Vehicle Back */}
              <div className="rounded-xl border border-border/60 bg-input/30 p-2.5 space-y-2">
                <span className="text-xs font-semibold text-muted-foreground block">Vehicle Rear Bumper</span>
                {proofs.start_back_url ? (
                  <a href={proofs.start_back_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border hover:border-cyan-500 transition-colors">
                    <img src={proofs.start_back_url} alt="Vehicle Back" className="h-36 w-full object-cover" />
                  </a>
                ) : (
                  <div className="h-36 flex items-center justify-center rounded-lg bg-card/40 text-xs text-muted-foreground">Not Uploaded</div>
                )}
              </div>
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-border/70 p-6 text-center text-xs text-muted-foreground">
              No pre-ride inspection photos uploaded yet by the driver.
            </div>
          )}
        </div>

        {/* Post-Ride Proofs (Stage 4) */}
        <div>
          <h3 className="text-sm font-semibold mb-3 flex items-center gap-2 text-emerald-300">
            <CheckCircle2 className="h-4 w-4" /> 2. Post-Ride Delivery Proofs (Job Completed)
          </h3>
          {hasPostRide ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* End Meter */}
              <div className="rounded-xl border border-border/60 bg-input/30 p-2.5 space-y-2">
                <span className="text-xs font-semibold text-muted-foreground block">End Odometer Meter</span>
                {proofs.end_meter_url ? (
                  <a href={proofs.end_meter_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border hover:border-emerald-500 transition-colors">
                    <img src={proofs.end_meter_url} alt="End Meter" className="h-40 w-full object-cover" />
                  </a>
                ) : (
                  <div className="h-40 flex items-center justify-center rounded-lg bg-card/40 text-xs text-muted-foreground">Not Uploaded</div>
                )}
              </div>

              {/* End Vehicle Back / Cargo */}
              <div className="rounded-xl border border-border/60 bg-input/30 p-2.5 space-y-2">
                <span className="text-xs font-semibold text-muted-foreground block">Cargo Area / Rear Condition</span>
                {proofs.end_back_url ? (
                  <a href={proofs.end_back_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border hover:border-emerald-500 transition-colors">
                    <img src={proofs.end_back_url} alt="End Back Cargo" className="h-40 w-full object-cover" />
                  </a>
                ) : (
                  <div className="h-40 flex items-center justify-center rounded-lg bg-card/40 text-xs text-muted-foreground">Not Uploaded</div>
                )}
              </div>
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-border/70 p-6 text-center text-xs text-muted-foreground">
              No delivery completion photos uploaded yet. Job is either in progress or booked.
            </div>
          )}
        </div>

        {/* Close Button */}
        <div className="flex justify-end pt-5 border-t border-border/60 mt-6">
          <button onClick={onClose} className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-6 py-2.5 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]">
            Close Viewer
          </button>
        </div>
      </div>
    </div>
  );
}


