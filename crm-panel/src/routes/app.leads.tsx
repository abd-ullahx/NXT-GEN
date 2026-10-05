import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { Search, Plus, Download, Filter, Sparkles, X, ChevronDown, Trash2, Eye, Activity, AlertTriangle, CheckCircle2, CalendarRange, MapPin, Truck, RefreshCcw } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { leadSources as initialSources, statusMeta, type LeadSource, type LeadStatus, type Lead } from "@/lib/mock-data";
import { lookupPostcode, type LocationGeocode } from "@/lib/api";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { LocationMap } from "@/components/LocationMap";
import { EmailTemplatesModal } from "@/components/EmailTemplatesModal";
import { LeadStatusPageModal } from "@/components/LeadStatusPageModal";

import {
  useLeadsQuery,
  useApproveLeadMutation,
  useCreateLeadMutation,
  useDeleteLeadMutation,
  useTrashedLeadsQuery,
  useRestoreLeadMutation,
  useForceDeleteLeadMutation,
  useSurveyRemindersQuery,
  useSkipSurveyReminderMutation,
  useStopSurveyRemindersMutation,
  useUpdateSurveyReminderMutation,
  useQuotationRemindersQuery,
  useSkipQuotationReminderMutation,
  useStopQuotationRemindersMutation,
  useUpdateQuotationReminderMutation,
} from "@/lib/queries";

export const Route = createFileRoute("/app/leads")({
  head: () => ({ meta: [{ title: "Leads — Next Gen Relocation CRM" }] }),
  component: Leads,
});

function Leads() {
  const [sources, setSources] = useState<LeadSource[]>(initialSources);
  const [activeSource, setActiveSource] = useState<string>("All");
  const [query, setQuery] = useState("");
  const deferredQuery = useDebouncedValue(query, 300);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [month, setMonth] = useState<string>(""); // "" = all months, else "YYYY-MM"
  const [addingSource, setAddingSource] = useState(false);
  const [newSource, setNewSource] = useState("");
  const [approvingId, setApprovingId] = useState<string | null>(null);

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 50;

  const [viewMode, setViewMode] = useState<"active" | "trash">("active");
  
  // TanStack Query with instant cache memory — fetch all active leads
  const { data: rawLeads = [], isLoading: loading, refetch, isRefetching } = useLeadsQuery({
    month: month || undefined,
  });

  const allLeads = useMemo(() => (Array.isArray(rawLeads) ? rawLeads : []), [rawLeads]);

  const { data: rawTrashedLeads = [], isLoading: trashedLoading } = useTrashedLeadsQuery();
  const allTrashedLeads = useMemo(() => (Array.isArray(rawTrashedLeads) ? rawTrashedLeads : []), [rawTrashedLeads]);
  const restoreMutation = useRestoreLeadMutation();
  const forceDeleteMutation = useForceDeleteLeadMutation();


  const approveMutation = useApproveLeadMutation();
  const createMutation = useCreateLeadMutation();
  const deleteMutation = useDeleteLeadMutation();
  const skipReminderMutation = useSkipSurveyReminderMutation();
  const stopRemindersMutation = useStopSurveyRemindersMutation();
  const updateSurveyReminderMutation = useUpdateSurveyReminderMutation();
  const skipQuotationReminderMutation = useSkipQuotationReminderMutation();
  const stopQuotationRemindersMutation = useStopQuotationRemindersMutation();
  const updateQuotationReminderMutation = useUpdateQuotationReminderMutation();

  // Modal State & Geocoding Map State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedLead, setSelectedLead] = useState<Lead | null>(null);
  const [statusLead, setStatusLead] = useState<Lead | null>(null);
  const [leadToDelete, setLeadToDelete] = useState<Lead | null>(null);
  const [activeDropdown, setActiveDropdown] = useState<string | null>(null);
  const [leadGeocode, setLeadGeocode] = useState<LocationGeocode | null>(null);
  const [searchingGeo, setSearchingGeo] = useState(false);
  const [emailModalLead, setEmailModalLead] = useState<Lead | null>(null);

  // Survey day reminder data — fetched when Check Status modal is open
  const { data: surveyReminders = [], isLoading: remindersLoading } = useSurveyRemindersQuery(
    statusLead?.id ?? null
  );
  const { data: quotationReminders = [], isLoading: quoteRemindersLoading } = useQuotationRemindersQuery(
    statusLead?.id ?? null
  );
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    source: "Website Form" as LeadSource,
    moveType: "3-Bed House → Detached",
    from: "",
    to: "",
    moveDate: new Date().toISOString().split("T")[0],
    estValue: 3500,
    priority: "Hot" as "Hot" | "Warm" | "Cold",
  });

  const handleApprove = async (lead: Lead) => {
    setApprovingId(lead.id);
    try {
      await approveMutation.mutateAsync(lead.id);
    } catch (err: any) {
      const msg = err?.response?.data?.error || err?.message || "Failed to send welcome email.";
      toast.error(`Approval Alert: ${msg}`);
    } finally {
      setApprovingId(null);
    }
  };

  // Build a list of the last 12 months for the month-wise filter (spec #9).
  const monthOptions = useMemo(() => {
    const opts: { value: string; label: string }[] = [];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
      const label = d.toLocaleString("en-GB", { month: "long", year: "numeric" });
      opts.push({ value, label });
    }
    return opts;
  }, []);

  useEffect(() => {
    if (selectedLead) {
      const target = selectedLead.from || selectedLead.to;
      if (target) {
        setSearchingGeo(true);
        lookupPostcode(target).then((res) => {
          setLeadGeocode(res);
          setSearchingGeo(false);
        });
      } else {
        setLeadGeocode(null);
      }
    } else {
      setLeadGeocode(null);
    }
  }, [selectedLead]);

  const handleCreateLead = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createMutation.mutateAsync(formData);
      setIsModalOpen(false);
      setFormData({
        name: "",
        email: "",
        phone: "",
        source: "Website Form",
        moveType: "3-Bed House → Detached",
        from: "",
        to: "",
        moveDate: new Date().toISOString().split("T")[0],
        estValue: 3500,
        priority: "Hot",
      });
    } catch (err) {
      console.error("Failed to create lead:", err);
    }
  };

  
  const handleDeleteLead = async () => {
    if (!leadToDelete) return;
    const targetId = leadToDelete.id;
    setLeadToDelete(null); // Close modal instantly for 0ms UI response delay
    
    try {
      if (viewMode === "trash") {
        await forceDeleteMutation.mutateAsync(targetId);
      } else {
        await deleteMutation.mutateAsync(targetId);
      }
    } catch (err) {
      console.error("Failed to delete lead:", err);
      toast.error("Error deleting lead.");
    }
  };
  
  const handleRestoreLead = async (id: string) => {
    try {
      await restoreMutation.mutateAsync(id);
    } catch (err) {
      console.error("Failed to restore lead:", err);
    }
  };


  const matchesSourceCategory = (source: string | undefined, category: string): boolean => {
    if (category === "All") return true;
    if (!source) return false;

    const s = source.toLowerCase().trim();
    const c = category.toLowerCase().trim();

    if (c === "getamover" || c === "gatemover") {
      return s.includes("getamover") || s.includes("gatemover");
    }
    if (c === "konnectyou") {
      return s.includes("konnectyou");
    }
    if (c === "website form" || c === "webform") {
      return s.includes("website") || s.includes("webform") || s.includes("web form");
    }
    if (c === "social platforms" || c === "social") {
      return s.includes("social") || s.includes("facebook") || s.includes("instagram") || s.includes("google");
    }
    if (c === "calls" || c === "call") {
      return s.includes("call") || s.includes("phone") || s.includes("outlook");
    }
    if (c === "refer" || c === "referral") {
      return s.includes("refer");
    }

    return s === c;
  };

  const matchesStatusFilter = (leadStatus: string, filter: string): boolean => {
    if (!filter || filter === "all") return true;
    const s = (leadStatus || "").toLowerCase();
    const f = filter.toLowerCase();
    if (f === "won") return s === "won" || s === "job-booked" || s === "completed";
    return s === f;
  };

  const filtered = useMemo(
    () =>
      (viewMode === 'active' ? allLeads : allTrashedLeads).filter(
        (l) =>
          matchesStatusFilter(l.status, statusFilter) &&
          matchesSourceCategory(l.source, activeSource) &&
          (String(l.name || "").toLowerCase().includes(deferredQuery.toLowerCase()) ||
            String(l.email || "").toLowerCase().includes(deferredQuery.toLowerCase()) ||
            String(l.moveType || "").toLowerCase().includes(deferredQuery.toLowerCase())),
      ),
    [allLeads, allTrashedLeads, viewMode, statusFilter, activeSource, deferredQuery],
  );

  // Reset page to 1 when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [deferredQuery, statusFilter, activeSource, viewMode]);

  const totalPages = Math.ceil(filtered.length / itemsPerPage);
  const paginatedLeads = filtered.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  return (
    <div className="space-y-6">
      
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Sparkles className="h-3.5 w-3.5" /> Pipeline
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Leads & Clients</h1>
          <p className="text-sm text-muted-foreground">Manage your incoming inquiries, quotations, and upcoming moves.</p>
        </div>
        
        <div className="flex bg-input/40 p-1 rounded-xl border border-border">
          <button 
            onClick={() => setViewMode("active")}
            className={`px-4 py-1.5 text-sm font-medium rounded-lg transition-colors ${viewMode === "active" ? "bg-gold text-black shadow-sm" : "text-muted-foreground hover:text-foreground"}`}
          >
            Active Leads
          </button>
          <button 
            onClick={() => setViewMode("trash")}
            className={`px-4 py-1.5 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5 ${viewMode === "trash" ? "bg-destructive text-white shadow-sm" : "text-muted-foreground hover:text-foreground"}`}
          >
            <Trash2 className="h-3.5 w-3.5" /> Trash
          </button>
        </div>
        
        <div className="flex items-center gap-3">
          {/* Status Filter */}
          <div className="relative">
            <Filter className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-8 rounded-lg border border-border/50 bg-background/50 pl-8 pr-8 text-xs outline-none focus:border-gold/50"
            >
              <option value="all">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="assigned">Driver Assigned</option>
              <option value="in_progress">In Progress</option>
              <option value="survey-completed">Survey Completed</option>
              <option value="won">Won / Completed</option>
              <option value="lost">Lost</option>
            </select>
          </div>
          {/* Search */}
          <div className="relative">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search leads..."
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              className="h-8 w-48 rounded-lg border border-border/50 bg-background/50 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
            />
          </div>

          <button
            onClick={() => refetch()}
            disabled={isRefetching}
            className="flex items-center gap-1.5 px-3 py-1.5 h-8 bg-background/50 border border-border/50 rounded-lg text-xs font-medium hover:bg-gold/10 hover:text-gold transition-colors disabled:opacity-50"
          >
            <RefreshCcw className={`h-3.5 w-3.5 ${isRefetching ? 'animate-spin' : ''}`} />
            Sync
          </button>
          
          {/* Add Lead */}
          <button
            onClick={() => {
              setSelectedLead(null);
              setIsModalOpen(true);
            }}
            className="flex h-8 items-center gap-1.5 rounded-lg bg-foreground px-3 text-xs font-medium text-background hover:bg-foreground/90 transition-colors"
          >
            <Plus className="h-3.5 w-3.5" /> Add Lead
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
        {(["All", ...sources] as string[]).map((s) => (
          <button
            key={s}
            onClick={() => setActiveSource(s)}
            className={`flex items-center gap-1.5 whitespace-nowrap rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
              activeSource === s
                ? "border-gold bg-gold/10 text-gold"
                : "border-border/50 bg-background/50 text-muted-foreground hover:bg-accent hover:text-foreground"
            }`}
          >
            {s}
            <span className="rounded-full bg-foreground/10 px-1.5 py-0.5 text-[10px]">
              {s === "All" ? allLeads.length : allLeads.filter((l) => matchesSourceCategory(l.source, s)).length}
            </span>
          </button>
        ))}
      </div>

      <GlassCard className="overflow-visible">
        <div className="overflow-visible">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border/50 bg-muted/20 text-muted-foreground">
                <th className="px-5 py-3 font-medium">Customer Details</th>
                <th className="px-5 py-3 font-medium">Move Summary</th>
                <th className="px-5 py-3 font-medium">Source</th>
                <th className="px-5 py-3 font-medium">Value</th>
                <th className="px-5 py-3 font-medium">Received</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/50">
              {paginatedLeads.map((l) => (
                <tr key={l.id} className="group transition-colors hover:bg-muted/20">
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">

                      <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-xs font-semibold text-gold">
                        {l.name ? l.name.split(" ").map((n: string) => n[0]).join("") : "L"}
                      </div>
                      <div>
                        <div className="font-medium">{l.name}</div>
                        <div className="text-xs text-muted-foreground">{l.email}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <div className="text-sm">{l.moveType}</div>
                    <div className="text-xs text-muted-foreground">{l.from} → {l.to}</div>
                  </td>
                  <td className="px-5 py-4 text-muted-foreground">{l.source}</td>
                  <td className="px-5 py-4 font-medium">£{Number(l.estValue).toLocaleString()}</td>
                  <td className="px-5 py-4 text-xs text-muted-foreground">
                    {l.createdAt ? new Date(l.createdAt).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—"}
                    <div className="text-[10px] opacity-70">{l.createdAgo}</div>
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex flex-col gap-1">
                      <span className={`text-xs font-semibold capitalize ${
                        l.status === "won" || l.status === "job-booked" || l.stage === "Booking Confirmed" ? "text-emerald-400" :
                        statusMeta[l.status as LeadStatus]?.tone || "text-gold"
                      }`}>
                        {l.status === "won" || l.status === "job-booked" || l.stage === "Booking Confirmed"
                          ? "Booking Confirmed"
                          : (statusMeta[l.status as LeadStatus]?.label || l.stage || l.status).replace(/-/g, " ")}
                      </span>
                    </div>
                  </td>
                  <td className="px-5 py-4 text-right relative">
                    <div className="flex items-center justify-end gap-2">
                      {/* Draft-approval gate (spec #2/#3): approving fires welcome + indicative quotation */}
                      {l.status === "draft" && (
                        <button
                          type="button"
                          disabled={approvingId === l.id}
                          onClick={() => handleApprove(l)}
                          className="flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-gold-bright to-gold-dim px-3 py-1.5 text-xs font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)] disabled:opacity-50"
                        >
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          {approvingId === l.id ? "Approving…" : "Approve"}
                        </button>
                      )}

                      {(l.status === "won" || l.stage === "Booking Confirmed" || l.stage === "Completed") && (
                        <Link
                          to="/app/jobs"
                          search={{ highlight: l.id }}
                          className="flex items-center gap-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/30 px-3 py-1.5 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/30 transition-all"
                        >
                          <Truck className="h-3.5 w-3.5" /> Assign Driver
                        </Link>
                      )}
                    <div className="relative inline-block text-left">
                      <button
                        type="button"
                        onClick={() => setActiveDropdown(activeDropdown === l.id ? null : l.id)}
                        className="flex items-center gap-1.5 rounded-lg border border-border bg-card/60 px-3 py-1.5 text-xs font-semibold text-foreground hover:border-gold/40 transition-all"
                      >
                        Actions <ChevronDown className="h-3.5 w-3.5" />
                      </button>

                      {activeDropdown === l.id && (
                        <div className="absolute right-0 z-30 mt-1.5 w-40 rounded-xl border border-border bg-card p-1.5 shadow-2xl backdrop-blur-xl animate-in fade-in zoom-in-95 duration-100">
                          
                            {viewMode === "trash" ? (
                              <>
                                <button
                                  onClick={() => {
                                    handleRestoreLead(l.id);
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-foreground hover:bg-success/15 hover:text-success transition-colors"
                                >
                                  <CheckCircle2 className="h-3.5 w-3.5 text-success" /> Restore Lead
                                </button>
                                <div className="my-1 border-t border-border/60" />
                                <button
                                  onClick={() => {
                                    setLeadToDelete(l);
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-destructive hover:bg-destructive/15 transition-colors"
                                >
                                  <Trash2 className="h-3.5 w-3.5" /> Permanently Delete
                                </button>
                              </>
                            ) : (
                              <>
                                <button
                                  onClick={() => {
                                    setSelectedLead(l);
                                    setIsModalOpen(true);
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-foreground hover:bg-gold/15 hover:text-gold transition-colors"
                                >
                                  <Eye className="h-3.5 w-3.5 text-gold" /> View Details
                                </button>
                                <button
                                  onClick={() => {
                                    window.open(`${import.meta.env.VITE_API_URL || "/api"}/leads/${l.id}/pdf`, "_blank");
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-foreground hover:bg-gold/15 hover:text-gold transition-colors"
                                >
                                  <Download className="h-3.5 w-3.5 text-gold" /> PDF Summary
                                </button>
                                <button
                                  onClick={() => {
                                    setStatusLead(l);
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-foreground hover:bg-gold/15 hover:text-gold transition-colors"
                                >
                                  <Activity className="h-3.5 w-3.5 text-gold" /> Check Status
                                </button>
                                <div className="my-1 border-t border-border/60" />
                                <button
                                  onClick={() => {
                                    setLeadToDelete(l);
                                    setActiveDropdown(null);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-destructive hover:bg-destructive/15 transition-colors"
                                >
                                  <Trash2 className="h-3.5 w-3.5" /> Move to Trash
                                </button>
                              </>
                            )}

                        </div>
                      )}
                    </div>
                    </div>
                  </td>
                </tr>
              ))}
              {paginatedLeads.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-sm text-muted-foreground">
                    No leads match your filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Controls */}
        {totalPages > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-border/50">
            <div className="text-xs text-muted-foreground">
              Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filtered.length)} of {filtered.length} leads
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="h-8 px-3 rounded-lg border border-border/50 bg-background/50 text-xs font-medium hover:bg-gold/10 hover:text-gold transition-colors disabled:opacity-50"
              >
                Previous
              </button>
              <div className="text-xs text-muted-foreground px-2">
                Page {currentPage} of {totalPages}
              </div>
              <button
                type="button"
                onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="h-8 px-3 rounded-lg border border-border/50 bg-background/50 text-xs font-medium hover:bg-gold/10 hover:text-gold transition-colors disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </GlassCard>

      {/* New Lead Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
            <button onClick={() => setIsModalOpen(false)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>
            <h2 className="font-display text-2xl font-semibold mb-1">Add New Lead</h2>
            <p className="text-xs text-muted-foreground mb-4">Save a new customer removal inquiry into MySQL.</p>
            <form onSubmit={handleCreateLead} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Full Name</label>
                  <input
                    required
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="e.g. Sarah Jenkins"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Email</label>
                  <input
                    required
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder="sarah@example.com"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Phone</label>
                  <input
                    required
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    placeholder="+44 7700 900555"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Lead Source</label>
                  <select
                    value={formData.source}
                    onChange={(e) => setFormData({ ...formData, source: e.target.value as LeadSource })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
                  >
                    {sources.map((s) => (
                      <option key={s} value={s} className="bg-card text-foreground">{s}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">From Location</label>
                  <input
                    required
                    value={formData.from}
                    onChange={(e) => setFormData({ ...formData, from: e.target.value })}
                    placeholder="e.g. Slough"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">To Location</label>
                  <input
                    required
                    value={formData.to}
                    onChange={(e) => setFormData({ ...formData, to: e.target.value })}
                    placeholder="e.g. Maidenhead"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Move Details</label>
                  <input
                    value={formData.moveType}
                    onChange={(e) => setFormData({ ...formData, moveType: e.target.value })}
                    placeholder="e.g. 3-Bed House"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Est Value (£)</label>
                  <input
                    type="number"
                    value={formData.estValue}
                    onChange={(e) => setFormData({ ...formData, estValue: Number(e.target.value) })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
                >
                  Save Lead to MySQL
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Lead Detail Modal */}
      {selectedLead && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
            <button onClick={() => setSelectedLead(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 mb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-sm font-semibold text-gold">
                {selectedLead.name ? selectedLead.name.split(" ").map((n) => n[0]).join("") : "L"}
              </div>
              <div>
                <h2 className="font-display text-2xl font-semibold leading-tight">{selectedLead.name || "Unknown Lead"}</h2>
                <p className="text-xs text-muted-foreground">
                  {selectedLead.source} · {statusMeta[selectedLead.status]?.label || selectedLead.status}
                </p>
              </div>
            </div>

            {/* Current Stage Indicator */}
            <div className="mb-4 rounded-xl border border-gold/30 bg-gold/10 p-3 flex items-center justify-between">
              <div>
                <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Current Stage</div>
                <div className="text-sm font-semibold text-gold">{selectedLead.stage || "Welcome Email"}</div>
              </div>
              <button
                onClick={() => {
                  setStatusLead(selectedLead);
                  setSelectedLead(null);
                }}
                className="rounded-lg bg-gold/20 px-3 py-1.5 text-xs font-semibold text-gold hover:bg-gold/30 transition-colors"
              >
                Check Stage Tracker →
              </button>
            </div>

            <div className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
              {[
                ["Email", selectedLead.email || "—"],
                ["Phone", selectedLead.phone || "—"],
                ["Move Type", selectedLead.moveType || "—"],
                ["Estimated Value", `£${Number(selectedLead.estValue).toLocaleString()}`],
                ["Moving From", selectedLead.from || "—"],
                ["Moving To", selectedLead.to || "—"],
                ["Move Date", selectedLead.moveDate || "—"],
                ["AI Score", `${selectedLead.aiScore}/10`],
                ["Priority", selectedLead.priority],
                ["Lead ID", selectedLead.id],
              ].map(([label, value]) => (
                <div key={label as string}>
                  <div className="text-xs uppercase tracking-wider text-muted-foreground">{label}</div>
                  <div className="mt-0.5 break-words font-medium">{value}</div>
                </div>
              ))}
            </div>

            {/* Postal Geocode & Interactive Map */}
            <div className="mt-4 border-t border-border/60 pt-4">
              <div className="flex items-center justify-between mb-2">
                <div className="text-xs font-semibold uppercase tracking-wider text-gold flex items-center gap-1.5">
                  <MapPin className="h-3.5 w-3.5" /> Location & Postal Map
                </div>
                <button
                  type="button"
                  disabled={searchingGeo}
                  onClick={async () => {
                    const searchTarget = selectedLead.from || selectedLead.to;
                    if (!searchTarget) return;
                    setSearchingGeo(true);
                    const res = await lookupPostcode(searchTarget);
                    if (res) setLeadGeocode(res);
                    setSearchingGeo(false);
                  }}
                  className="text-xs text-gold hover:underline disabled:opacity-50"
                >
                  {searchingGeo ? "Locating..." : "Auto-Geocode Postal Code"}
                </button>
              </div>

              {leadGeocode ? (
                <LocationMap
                  latitude={leadGeocode.latitude}
                  longitude={leadGeocode.longitude}
                  label={`Geocoded Postcode: ${leadGeocode.postcode} (${leadGeocode.district || leadGeocode.region || 'UK'})`}
                />
              ) : (
                <div className="rounded-xl border border-dashed border-border/60 p-4 text-center text-xs text-muted-foreground bg-card/20">
                  Postcode location map not fetched yet. Click "Auto-Geocode Postal Code" to locate on map.
                </div>
              )}
            </div>

            <div className="flex justify-end gap-2 pt-6">
              <button
                type="button"
                onClick={() => setSelectedLead(null)}
                className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
              >
                Close
              </button>
              {selectedLead.phone && (
                <a
                  href={`tel:${selectedLead.phone}`}
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
                >
                  Call Lead
                </a>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Email Templates Library Modal */}
      {emailModalLead && (
        <EmailTemplatesModal
          open={!!emailModalLead}
          onOpenChange={(open) => !open && setEmailModalLead(null)}
          leadId={emailModalLead.id}
          leadName={emailModalLead.name}
          leadEmail={emailModalLead.email}
        />
      )}

      {/* Check Status Full Page Lifecycle Stage Modal */}
      {statusLead && (
        <LeadStatusPageModal
          lead={statusLead}
          onClose={() => setStatusLead(null)}
          surveyReminders={surveyReminders}
          remindersLoading={remindersLoading}
          onSkipReminder={(reminderId) =>
            skipReminderMutation.mutate({ leadId: statusLead.id, reminderId })
          }
          onStopReminders={() => stopRemindersMutation.mutate(statusLead.id)}
          onUpdateReminder={(reminderId, scheduledAt) =>
            updateSurveyReminderMutation.mutate({ leadId: statusLead.id, reminderId, scheduledAt })
          }
          quotationReminders={quotationReminders}
          quoteRemindersLoading={quoteRemindersLoading}
          onSkipQuotationReminder={(reminderId) =>
            skipQuotationReminderMutation.mutate({ leadId: statusLead.id, reminderId })
          }
          onStopQuotationReminders={() => stopQuotationRemindersMutation.mutate(statusLead.id)}
          onUpdateQuotationReminder={(reminderId, scheduledAt) =>
            updateQuotationReminderMutation.mutate({ leadId: statusLead.id, reminderId, scheduledAt })
          }
          onOpenEmailTemplates={() => {
            setEmailModalLead(statusLead);
            setStatusLead(null);
          }}
        />
      )}

      {/* Admin Delete Lead Confirmation Modal */}
      {leadToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150 border border-destructive/40">
            <button onClick={() => setLeadToDelete(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 mb-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-destructive/20 text-destructive">
                <AlertTriangle className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">
                  {viewMode === "trash" ? "Permanently Delete Lead" : "Move to Trash"}
                </h2>
                <p className="text-xs text-muted-foreground">Admin confirmation required.</p>
              </div>
            </div>

            <p className="text-xs text-muted-foreground mb-4">
              {viewMode === "trash" ? (
                <>Are you sure you want to <strong>permanently delete</strong> lead <strong className="text-foreground">{leadToDelete.name}</strong> ({leadToDelete.id}) from the database? This action cannot be undone.</>
              ) : (
                <>Are you sure you want to move <strong className="text-foreground">{leadToDelete.name}</strong> ({leadToDelete.id}) to the trash? You can restore it later from the Trash view.</>
              )}
            </p>

            <div className="flex justify-end gap-3 pt-3">
              <button
                type="button"
                onClick={() => setLeadToDelete(null)}
                className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDeleteLead}
                className="rounded-xl bg-destructive px-5 py-2 text-xs font-semibold text-destructive-foreground hover:bg-destructive/90 transition-all shadow-md"
              >
                {viewMode === "trash" ? "Permanently Delete" : "Move to Trash"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
