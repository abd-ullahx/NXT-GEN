import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import {
  UserCheck,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  Eye,
  Mail,
  Phone,
  MapPin,
  Calendar,
  Sparkles,
  FileText,
  Building,
  ShieldCheck,
  Check,
  X,
  RefreshCcw,
  Download
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { fetchLeads, approveSurvey } from "@/lib/api";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useAllLeadsQuery, useApproveSurveyMutation } from "@/lib/queries";
import { SurveyReportView } from "@/components/SurveyReportView";

export const Route = createFileRoute("/app/clients")({
  head: () => ({ meta: [{ title: "Clients — Next Gen Relocation CRM" }] }),
  component: ClientsPage,
});

export interface ClientRecord {
  id: string;
  clientNumber: string;
  name: string;
  email: string;
  phone: string;
  moveType: string;
  from: string;
  to: string;
  moveDate: string;
  surveyDate: string;
  surveyTime: string;
  surveyType: string;
  surveyNotes: string;
  surveyStatus: "Required" | "Booked" | "Completed" | "Approved";
  estValue: number;
  priority: string;
  status: string;
  stage?: string;
  leadId: string;
  approvedAt?: string;
  surveyApprovedAt?: string;
  surveyorName?: string;
  surveyorEmail?: string;
  surveyorReportNotes?: string;
  surveyorMedia?: string[];
  surveyorCompletedAt?: string;
}

function ClientsPage() {
  const { data: rawLeads = [], isLoading: loading, refetch, isRefetching } = useAllLeadsQuery();
  const approveSurveyMutation = useApproveSurveyMutation();

  const [searchQuery, setSearchQuery] = useState("");
  const debouncedSearch = useDebouncedValue(searchQuery, 300);
  const [surveyStageFilter, setSurveyStageFilter] = useState<string>("all");
  
  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 50;

  const [selectedClient, setSelectedClient] = useState<ClientRecord | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const clients: ClientRecord[] = useMemo(() => {
    const leads = Array.isArray(rawLeads) ? rawLeads : [];
    return leads
      .filter(
        (l: any) =>
          l.surveyApprovedAt ||
          l.surveyBookedAt ||
          l.surveyRequestedDate ||
          l.surveyStatus === "booked" ||
          l.surveyStatus === "approved" ||
          l.status === "survey-booked" ||
          l.status === "survey-approved" ||
          l.status === "quote-approved" ||
          l.status === "won" ||
          l.status === "assigned" ||
          l.stage === "Survey" ||
          l.stage === "Booking Confirmed" ||
          l.stage === "Job" ||
          l.quotation_approved_at ||
          l.initial_deposit_paid
      )
      .map((l: any) => {
        const surveyDateStr = l.surveyRequestedDate || (l.surveyBookedAt ? l.surveyBookedAt.split("T")[0] : null);
        const todayStr = new Date().toISOString().split("T")[0];

        let stage: "Required" | "Booked" | "Approved" = "Booked";
        if (l.surveyApprovedAt || l.surveyStatus === "approved" || (surveyDateStr && surveyDateStr <= todayStr)) {
          stage = "Approved";
        } else {
          stage = "Booked";
        }

        return {
          id: `CL-${l.id}`,
          clientNumber: `CLI-2026-${String(l.id).padStart(3, "0")}`,
          name: l.name,
          email: l.email,
          phone: l.phone,
          moveType: l.moveType,
          from: l.from || "—",
          to: l.to || "—",
          moveDate: l.moveDate || "TBD",
          surveyDate: surveyDateStr || "TBD",
          surveyTime: l.surveyRequestedTimeRange || "TBD",
          surveyType: l.surveyType || "physical",
          surveyNotes: l.surveyNotes || "—",
          surveyStatus: stage,
          estValue: Number(l.estValue) || 2500,
          priority: l.priority || "Warm",
          status: l.status,
          stage: l.stage,
          leadId: l.id,
          approvedAt: l.approvedAt,
          surveyApprovedAt: l.surveyApprovedAt,
          surveyorName: l.surveyorName,
          surveyorEmail: l.surveyorEmail,
          surveyorReportNotes: l.surveyorReportNotes,
          surveyorMedia: Array.isArray(l.surveyorMedia) ? l.surveyorMedia : [],
          surveyorCompletedAt: l.surveyorCompletedAt,
        };
      });
  }, [rawLeads]);

  const handleApproveSurvey = async (client: ClientRecord) => {
    setBusyId(client.id);
    try {
      await approveSurveyMutation.mutateAsync(client.leadId);
    } catch (err) {
      console.error("Failed to approve survey:", err);
    } finally {
      setBusyId(null);
    }
  };

  const filteredClients = useMemo(() => {
    const q = debouncedSearch.toLowerCase();
    return clients.filter((c) => {
      const matchesSearch =
        c.name.toLowerCase().includes(q) ||
        c.clientNumber.toLowerCase().includes(q) ||
        c.email.toLowerCase().includes(q) ||
        c.from.toLowerCase().includes(q) ||
        c.to.toLowerCase().includes(q);

      const matchesStage =
        surveyStageFilter === "all" ||
        (surveyStageFilter === "confirmed" && (c.status === "won" || c.status === "job-booked" || c.stage === "Booking Confirmed")) ||
        c.surveyStatus.toLowerCase() === surveyStageFilter.toLowerCase();

      return matchesSearch && matchesStage;
    });
  }, [clients, debouncedSearch, surveyStageFilter]);

  // Reset page when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [debouncedSearch, surveyStageFilter]);

  const totalPages = Math.ceil(filteredClients.length / itemsPerPage);
  const paginatedClients = filteredClients.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  const stats = useMemo(() => {
    return {
      total: clients.length,
      booked: clients.filter((c) => c.surveyStatus === "Booked").length,
      completed: clients.filter((c) => c.surveyStatus === "Completed" || c.surveyStatus === "Approved").length,
      value: clients.reduce((acc, c) => acc + (c.estValue || 0), 0),
    };
  }, [clients]);

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <UserCheck className="h-3.5 w-3.5" /> Client Directory
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Clients</h1>
          <p className="text-sm text-muted-foreground">
            Leads converted to Clients following survey booking & completed pre-move assessments.
          </p>
        </div>
      </div>

      {/* Survey Stage Progression Pipeline Banner */}
      <GlassCard className="p-5 border-gold/30 bg-gradient-to-r from-gold/10 via-card to-card">
        <h3 className="text-xs uppercase tracking-wider text-gold font-bold mb-3 flex items-center gap-2">
          <Sparkles className="h-4 w-4" /> Client Conversion Pipeline (3-Stage Survey Lifecycle)
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
          <div className="flex items-center gap-3 rounded-xl border border-border/60 bg-muted/20 p-3">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gold/15 text-gold font-bold">1</div>
            <div>
              <div className="font-semibold text-foreground">1. Survey Required</div>
              <div className="text-muted-foreground text-[11px]">Initial lead created & welcome email dispatched</div>
            </div>
          </div>
          <div className="flex items-center gap-3 rounded-xl border border-gold/40 bg-gold/10 p-3">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gold text-primary-foreground font-bold">2</div>
            <div>
              <div className="font-semibold text-gold">2. Survey Booked → Converts to Client</div>
              <div className="text-muted-foreground text-[11px]">Customer selects date, time & assessment mode</div>
            </div>
          </div>
          <div className="flex items-center gap-3 rounded-xl border border-success/40 bg-success/10 p-3">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-success text-primary-foreground font-bold">3</div>
            <div>
              <div className="font-semibold text-success">3. Survey Completed</div>
              <div className="text-muted-foreground text-[11px]">Assessment approved & verified for final quote</div>
            </div>
          </div>
        </div>
      </GlassCard>

      {/* Metric Summary Cards */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Total Active Clients</span>
            <UserCheck className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">{stats.total}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Leads with survey data attached</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Survey Booked</span>
            <Clock className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-gold">{stats.booked}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Awaiting inspection visit</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Survey Approved</span>
            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-success">{stats.completed}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Verified pre-move assessments</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Pipeline Value</span>
            <ShieldCheck className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">
            £{stats.value.toLocaleString()}
          </div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Estimated revenue of survey clients</div>
        </GlassCard>
      </div>

      {/* Search & Stage Filters */}
      <GlassCard className="p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="flex flex-1 max-w-md items-center gap-2">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search clients..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-input/30 border border-border/50 rounded-xl py-2 pl-9 pr-4 text-sm focus:outline-none focus:border-gold/50"
            />
          </div>
          
          <button
            onClick={() => refetch()}
            disabled={isRefetching}
            className="flex items-center gap-1.5 px-3 py-1.5 h-9 bg-background/50 border border-border/50 rounded-lg text-sm font-medium hover:bg-gold/10 hover:text-gold transition-colors disabled:opacity-50"
          >
            <RefreshCcw className={`h-4 w-4 ${isRefetching ? 'animate-spin' : ''}`} />
            Sync
          </button>
        </div>

        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-muted-foreground" />
          <select
            value={surveyStageFilter}
            onChange={(e) => setSurveyStageFilter(e.target.value)}
            className="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
          >
            <option value="all" className="bg-card">All Stages</option>
            <option value="booked" className="bg-card">Survey Booked</option>
            <option value="approved" className="bg-card">Survey Approved</option>
            <option value="confirmed" className="bg-card">Booking Confirmed</option>
          </select>
        </div>
      </GlassCard>

      {/* Client Roster Table */}
      <GlassCard className="overflow-hidden">
        <div className="border-b border-border/60 p-5 flex items-center justify-between">
          <div>
            <h2 className="font-display text-xl font-semibold">Client Roster</h2>
            <p className="text-xs text-muted-foreground">Converted leads carrying full survey specs & relocation details</p>
          </div>
          <span className="text-xs font-mono text-gold bg-gold/10 px-3 py-1 rounded-full border border-gold/20">
            {filteredClients.length} Clients
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
              <tr>
                <th className="px-5 py-3">Client Ref</th>
                <th className="px-5 py-3">Client Details</th>
                <th className="px-5 py-3">Route / Move</th>
                <th className="px-5 py-3">Survey Schedule</th>
                <th className="px-5 py-3">Survey Mode</th>
                <th className="px-5 py-3">Current Stage</th>
                <th className="px-5 py-3 text-right">Est Value</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/40">
              {paginatedClients.map((client) => (
                <tr key={client.id} className="hover:bg-sidebar-accent/50 transition-colors">
                  <td className="px-5 py-4 font-mono font-medium text-gold">{client.clientNumber}</td>
                  <td className="px-5 py-4">
                    <div className="font-medium text-foreground">{client.name}</div>
                    <div className="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                      <span>{client.email}</span>
                      <span>•</span>
                      <span>{client.phone}</span>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <div className="text-xs font-medium text-foreground">{client.from} → {client.to}</div>
                    <div className="text-[11px] text-muted-foreground">{client.moveType}</div>
                  </td>
                  <td className="px-5 py-4 text-xs">
                    <div className="font-medium text-gold">{client.surveyDate}</div>
                    <div className="text-muted-foreground">{client.surveyTime}</div>
                  </td>
                  <td className="px-5 py-4 text-xs capitalize">
                    <span className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 border border-border bg-card">
                      {client.surveyType}
                    </span>
                  </td>
                  <td className="px-5 py-4">
                    <span
                      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
                        client.status === "won" || client.status === "job-booked" || client.stage === "Booking Confirmed"
                          ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                          : client.surveyStatus === "Approved" || client.surveyStatus === "Completed"
                          ? "bg-success/15 text-success border border-success/30"
                          : "bg-gold/15 text-gold border border-gold/30 animate-pulse"
                      }`}
                    >
                      {client.status === "won" || client.status === "job-booked" || client.stage === "Booking Confirmed" ? (
                        <>
                          <CheckCircle2 className="h-3 w-3" /> Booking Confirmed
                        </>
                      ) : client.surveyStatus === "Approved" || client.surveyStatus === "Completed" ? (
                        <>
                          <CheckCircle2 className="h-3 w-3" /> Survey Approved
                        </>
                      ) : (
                        <>
                          <Clock className="h-3 w-3" /> Survey Booked
                        </>
                      )}
                    </span>
                  </td>
                  <td className="px-5 py-4 text-right font-medium text-foreground">
                    £{client.estValue.toLocaleString()}
                  </td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {client.surveyStatus === "Booked" && (
                        <button
                          disabled={busyId === client.id}
                          onClick={() => handleApproveSurvey(client)}
                          className="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-gold-bright to-gold-dim px-2.5 py-1.5 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50 transition-all"
                        >
                          <Check className="h-3.5 w-3.5" /> {busyId === client.id ? "Marking…" : "Mark Completed"}
                        </button>
                      )}
                      <button
                        onClick={() => setSelectedClient(client)}
                        className="inline-flex items-center gap-1 rounded-lg border border-border bg-card/60 px-2.5 py-1.5 text-xs text-muted-foreground hover:border-gold/50 hover:text-gold transition-colors"
                      >
                        <Eye className="h-3.5 w-3.5" /> Full Data
                      </button>
                    </div>
                  </td>
                </tr>
              ))}

              {paginatedClients.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-5 py-12 text-center text-sm text-muted-foreground">
                    {loading ? "Loading client records..." : "No converted survey clients found."}
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
              Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredClients.length)} of {filteredClients.length} clients
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

      {/* Client Full Profile Modal */}
      {selectedClient && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
          <div className="glass-card w-full max-w-4xl rounded-3xl p-6 md:p-8 relative animate-in fade-in zoom-in duration-150 border border-gold/30 bg-[#0E1017] shadow-2xl my-6 max-h-[90vh] overflow-y-auto space-y-6">
            <button
              onClick={() => setSelectedClient(null)}
              className="absolute right-5 top-5 text-muted-foreground hover:text-foreground p-1 rounded-lg border border-border/40 hover:bg-card transition-colors"
            >
              <X className="h-5 w-5" />
            </button>

            {/* Printable Container */}
            <div id="client-printable-area" className="space-y-6">
              {/* Header */}
              <div className="flex flex-col sm:flex-row sm:items-center gap-4 border-b border-border/60 pb-5">
                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gold/20 text-gold font-mono font-bold text-lg border border-gold/30">
                  CLI
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-2xl font-bold text-foreground">{selectedClient.name}</h2>
                    <span className="rounded-full bg-gold/20 text-gold px-3 py-0.5 text-xs font-mono font-bold border border-gold/30">
                      {selectedClient.clientNumber}
                    </span>
                  </div>
                  <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground mt-1">
                    <span className="flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-gold" /> {selectedClient.email}</span>
                    <span className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5 text-gold" /> {selectedClient.phone}</span>
                  </div>
                </div>
              </div>

              {/* Grid for Move Route & Assessment */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                {/* Relocation Summary */}
                <div className="rounded-2xl bg-card/40 p-4 border border-border/40 space-y-2">
                  <h4 className="font-bold text-gold uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                    <MapPin className="h-3.5 w-3.5" /> Move Route & Specs
                  </h4>
                  <div>
                    <span className="text-muted-foreground text-[10px] block uppercase">Origin → Destination</span>
                    <span className="font-semibold text-foreground text-sm block mt-0.5 break-words">{selectedClient.from} → {selectedClient.to}</span>
                  </div>
                  <div className="grid grid-cols-2 gap-2 pt-1">
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Property / Move Type</span>
                      <span className="font-semibold text-foreground">{selectedClient.moveType}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Scheduled Move Date</span>
                      <span className="font-bold text-gold">{selectedClient.moveDate}</span>
                    </div>
                  </div>
                </div>

                {/* Survey Data & Status */}
                <div className="rounded-2xl bg-card/40 p-4 border border-border/40 space-y-2">
                  <h4 className="font-bold text-gold uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5" /> Survey & Assessment Record
                  </h4>
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Survey Date</span>
                      <span className="font-bold text-gold">{selectedClient.surveyDate}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Time Slot</span>
                      <span className="font-semibold text-foreground">{selectedClient.surveyTime}</span>
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2 pt-1">
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Assessment Type</span>
                      <span className="font-semibold capitalize text-foreground">{selectedClient.surveyType} Survey</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground text-[10px] block uppercase">Survey Stage</span>
                      <span className="font-bold text-emerald-400">{selectedClient.surveyStatus}</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Customer Notes */}
              {selectedClient.surveyNotes && (
                <div className="rounded-2xl bg-card/40 p-4 border border-border/40 text-xs">
                  <span className="text-gold text-[10px] uppercase block font-bold tracking-wider mb-1">Customer Notes & Requirements</span>
                  <p className="text-muted-foreground leading-relaxed whitespace-pre-wrap">{selectedClient.surveyNotes}</p>
                </div>
              )}

              {/* Assigned Surveyor & Inspection Findings */}
              <div className="rounded-2xl bg-card/40 p-5 border border-gold/30 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-3">
                  <h4 className="font-bold text-gold uppercase tracking-wider text-xs flex items-center gap-1.5">
                    <UserCheck className="h-4 w-4" /> Assigned Surveyor & Filed On-Site Inspection
                  </h4>
                  {selectedClient.surveyorName ? (
                    <span className="text-xs font-semibold text-foreground bg-gold/10 border border-gold/20 px-3 py-1 rounded-full">
                      {selectedClient.surveyorName} ({selectedClient.surveyorEmail})
                    </span>
                  ) : (
                    <span className="text-xs text-muted-foreground italic">No Surveyor Assigned</span>
                  )}
                </div>

                {/* Structured Inspection Report Formatter */}
                {selectedClient.surveyorReportNotes ? (
                  <div className="space-y-2">
                    <span className="text-[10px] uppercase tracking-wider text-gold font-bold block">
                      Structured On-Site Room Inventory & Inspection Findings
                    </span>
                    <SurveyReportView
                      reportNotes={selectedClient.surveyorReportNotes}
                      propertyType={selectedClient.moveType}
                    />
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground italic p-3 bg-background/30 rounded-xl">
                    On-site inspection report notes pending surveyor visit.
                  </p>
                )}

                {/* Surveyor Uploaded Media Gallery */}
                {selectedClient.surveyorMedia && selectedClient.surveyorMedia.length > 0 && (
                  <div className="pt-2 border-t border-border/40">
                    <span className="text-[10px] uppercase tracking-wider text-gold font-bold block mb-2">
                      Surveyor Uploaded Photos & Walkthrough Media ({selectedClient.surveyorMedia.length})
                    </span>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 max-h-48 overflow-y-auto p-1 border border-border/40 rounded-xl bg-black/40">
                      {selectedClient.surveyorMedia.map((mediaUrl, idx) => (
                        <a
                          key={idx}
                          href={mediaUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="group relative rounded-xl overflow-hidden border border-gold/30 h-24 bg-black/60 hover:border-gold transition-all block flex items-center justify-center"
                        >
                          {mediaUrl.startsWith("data:image") || mediaUrl.match(/\.(jpeg|jpg|png|webp)$/i) ? (
                            <img src={mediaUrl} alt={`Client Survey Media ${idx}`} className="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                          ) : mediaUrl.startsWith("data:video") || mediaUrl.match(/\.(mp4|webm|mov)$/i) ? (
                            <video src={mediaUrl} className="w-full h-full object-cover" />
                          ) : (
                            <div className="p-2 text-[10px] text-gold text-center truncate font-medium">View Media {idx + 1}</div>
                          )}
                        </a>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Modal Controls */}
            <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-border/60">
              <button
                onClick={() => {
                  const printWindow = window.open("", "_blank");
                  if (!printWindow) return;

                  const mediaHtml = selectedClient.surveyorMedia && selectedClient.surveyorMedia.length > 0 ? `
                    <div style="margin-top: 24px; page-break-inside: avoid;">
                      <h4 style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #8a6d1c; letter-spacing: 0.5px; margin: 0 0 10px;">Attached Survey Media (${selectedClient.surveyorMedia.length} items)</h4>
                      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                        ${selectedClient.surveyorMedia.map((m, i) => `
                          <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; height: 80px; background: #f8fafc; text-align: center;">
                            ${m.startsWith("data:image") || m.match(/\.(jpeg|jpg|png|webp)$/i) ? `<img src="${m}" style="width: 100%; height: 100%; object-fit: cover;" />` : `<div style="padding: 25px 5px; font-size: 10px; color: #64748b;">Media ${i + 1}</div>`}
                          </div>
                        `).join("")}
                      </div>
                    </div>
                  ` : "";

                  printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                      <head>
                        <title>Client Record - ${selectedClient.name} (${selectedClient.clientNumber})</title>
                        <style>
                          @page { size: A4; margin: 15mm; }
                          * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
                          body { background: #ffffff; color: #0f172a; margin: 0; padding: 0; font-size: 12px; line-height: 1.5; }
                          .pdf-container { max-width: 800px; margin: 0 auto; padding: 10px; }
                          .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #c9a84c; padding-bottom: 16px; margin-bottom: 20px; }
                          .brand { font-size: 20px; font-weight: 800; color: #8a6d1c; letter-spacing: 1px; }
                          .brand-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
                          .badge { display: inline-block; background: #fefce8; border: 1px solid #fef08a; color: #8a6d1c; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; font-family: monospace; }
                          
                          .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
                          .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
                          .card-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #8a6d1c; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
                          
                          .info-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
                          .info-label { color: #64748b; font-size: 11px; }
                          .info-val { font-weight: 600; color: #0f172a; font-size: 11px; text-align: right; }
                          .info-val-highlight { font-weight: 700; color: #8a6d1c; font-size: 11px; text-align: right; }
                          
                          .section-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
                          .report-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
                          .metric-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
                          .metric-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; }
                          .metric-val { font-size: 12px; font-weight: 700; color: #0f172a; margin-top: 2px; }

                          .notes-box { background: #fefce8; border: 1px border-dashed #fef08a; border-radius: 8px; padding: 12px; color: #334155; font-size: 11px; margin-top: 10px; }

                          .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 12px; text-align: center; font-size: 10px; color: #94a3b8; }
                          
                          @media print {
                            body { background: #fff; }
                            .pdf-container { width: 100%; max-width: none; padding: 0; }
                          }
                        </style>
                      </head>
                      <body>
                        <div class="pdf-container">
                          <!-- Header -->
                          <div class="header">
                            <div>
                              <div class="brand">✨ NEXT GEN RELOCATION LTD</div>
                              <div class="brand-sub">7 Donnington Road, Reading, RG15NE, UK · support@nextgenrelocation.co.uk</div>
                            </div>
                            <div style="text-align: right;">
                              <div class="badge">${selectedClient.clientNumber}</div>
                              <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Generated: ${new Date().toLocaleDateString()}</div>
                            </div>
                          </div>

                          <!-- Client Profile Details -->
                          <div class="card" style="margin-bottom: 16px;">
                            <div class="card-title">👤 CLIENT INFORMATION</div>
                            <div class="grid-2" style="margin-bottom: 0;">
                              <div>
                                <div class="info-row"><span class="info-label">Full Name:</span> <span class="info-val">${selectedClient.name}</span></div>
                                <div class="info-row"><span class="info-label">Email Address:</span> <span class="info-val">${selectedClient.email}</span></div>
                                <div class="info-row"><span class="info-label">Phone Contact:</span> <span class="info-val">${selectedClient.phone}</span></div>
                              </div>
                              <div>
                                <div class="info-row"><span class="info-label">Current Stage:</span> <span class="info-val-highlight">${selectedClient.surveyStatus}</span></div>
                                <div class="info-row"><span class="info-label">Assigned Surveyor:</span> <span class="info-val">${selectedClient.surveyorName || 'Assigned Coordinator'}</span></div>
                                <div class="info-row"><span class="info-label">Move Date:</span> <span class="info-val-highlight">${selectedClient.moveDate}</span></div>
                              </div>
                            </div>
                          </div>

                          <!-- Route & Assessment Specs -->
                          <div class="grid-2">
                            <div class="card">
                              <div class="card-title">📍 MOVE ROUTE & SPECS</div>
                              <div class="info-row"><span class="info-label">Origin:</span> <span class="info-val" style="max-width: 180px;">${selectedClient.from}</span></div>
                              <div class="info-row"><span class="info-label">Destination:</span> <span class="info-val" style="max-width: 180px;">${selectedClient.to}</span></div>
                              <div class="info-row"><span class="info-label">Property Type:</span> <span class="info-val">${selectedClient.moveType}</span></div>
                            </div>

                            <div class="card">
                              <div class="card-title">📋 SURVEY & ASSESSMENT RECORD</div>
                              <div class="info-row"><span class="info-label">Survey Date:</span> <span class="info-val-highlight">${selectedClient.surveyDate}</span></div>
                              <div class="info-row"><span class="info-label">Time Slot:</span> <span class="info-val">${selectedClient.surveyTime}</span></div>
                              <div class="info-row"><span class="info-label">Survey Mode:</span> <span class="info-val" style="text-transform: capitalize;">${selectedClient.surveyType} Survey</span></div>
                            </div>
                          </div>

                          ${selectedClient.surveyNotes ? `
                            <div class="card" style="margin-bottom: 16px;">
                              <div class="card-title">💬 CUSTOMER NOTES & REQUIREMENTS</div>
                              <div style="font-size: 11px; color: #475569; white-space: pre-wrap;">${selectedClient.surveyNotes}</div>
                            </div>
                          ` : ''}

                          <!-- Surveyor Filed Inspection Notes -->
                          <div class="section-box">
                            <div class="card-title" style="margin-bottom: 12px;">🔍 ON-SITE INSPECTION FINDINGS & INVENTORY REPORT</div>
                            ${selectedClient.surveyorReportNotes ? `
                              <div class="notes-box">
                                <div style="font-weight: 700; color: #8a6d1c; margin-bottom: 4px; font-size: 10px; text-transform: uppercase;">Surveyor Field Notes:</div>
                                <div style="line-height: 1.6;">${selectedClient.surveyorReportNotes.replace(/\n/g, '<br>')}</div>
                              </div>
                            ` : `
                              <div style="color: #94a3b8; font-style: italic; font-size: 11px;">No survey report notes filed yet.</div>
                            `}
                          </div>

                          ${mediaHtml}

                          <!-- Footer -->
                          <div class="footer">
                            Next Gen Relocation Ltd · Official Client Assessment Record · Page 1 of 1
                          </div>
                        </div>

                        <script>
                          window.onload = function() {
                            window.print();
                            window.close();
                          };
                        </script>
                      </body>
                    </html>
                  `);
                  printWindow.document.close();
                }}
                className="flex items-center gap-1.5 rounded-xl border border-gold/40 bg-gold/10 px-4 py-2.5 text-xs font-semibold text-gold hover:bg-gold/20 transition-all"
              >
                <Download className="h-4 w-4" /> Download / Export Client PDF
              </button>

              <button
                onClick={() => setSelectedClient(null)}
                className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-6 py-2.5 text-xs font-bold text-black hover:shadow-[var(--shadow-gold)] transition-all"
              >
                Close Client Overview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
