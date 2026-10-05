import { createFileRoute } from '@tanstack/react-router'
import { useState, useEffect, useMemo } from "react";
import { toast } from "sonner";
import {
  ClipboardCheck,
  Search,
  Filter,
  Calendar,
  Clock,
  CheckCircle2,
  X,
  Eye,
  Check,
  Video,
  Home,
  Monitor,
  Send,
  Key,
  MoreHorizontal,
  Users,
  ImageIcon,
  RefreshCcw,
  Pencil,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { fetchLeads, approveSurvey, assignSurveyor, fetchTeamMembers, createVideoCallRoom, fetchVideoCallStatus } from "@/lib/api";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { VideoCallModal } from "@/components/VideoCallModal";
import { VideoUploadModal } from "@/components/VideoUploadModal";

export const Route = createFileRoute("/app/surveys")({
  head: () => ({ meta: [{ title: "Surveys — Next Gen CRM" }] }),
  component: SurveysPage,
});

type SurveyType = "physical" | "virtual" | "video";

export interface Survey {
  id: string;
  surveyNumber: string;
  clientName: string;
  clientEmail: string;
  clientPhone: string;
  surveyDate: string;
  surveyTime: string;
  surveyType: SurveyType;
  propertyType: string;
  location: string;
  status: "Scheduled" | "Approved" | "Pending";
  notes: string;
  leadId?: string;
  surveyorName?: string;
  surveyorEmail?: string;
  surveyorReportNotes?: string;
  surveyorMedia?: string[];
  surveyorCompletedAt?: string;
  cargoVolume?: string;
  totalBoxes?: string;
  recommendedVehicle?: string;
  packingService?: string;
  accessOrigin?: string;
  liftAvailable?: boolean;
  parkingAvailable?: boolean;
  customAccessFields?: any[];
  requiresDisassembly?: boolean;
  bedsQuantity?: number;
  wardrobesQuantity?: number;
  customDismantleItems?: any[];
  hasFragileItems?: boolean;
  fineArtPaintings?: boolean;
  pianoAntiqueItems?: boolean;
  customFragileFields?: any[];
  specialInstructions?: string;
  surveyorFindings?: string;
  videoCallRoomId?: string;
  videoCallStatus?: string;
  videoCallToken?: string;
  appUserPassword?: string;
  credentialsSentAt?: string;
  rescheduleProposedDate?: string;
  rescheduleProposedTime?: string;
  rescheduleStatus?: string;
  rescheduleToken?: string;
}

const surveyTypeMeta: Record<SurveyType, { label: string; icon: typeof Home; tone: string }> = {
  physical: { label: "Physical Visit", icon: Home, tone: "bg-gold/15 text-gold border-gold/30" },
  virtual: { label: "Virtual (Live)", icon: Monitor, tone: "bg-chart-3/15 text-chart-3 border-chart-3/30" },
  video: { label: "Video Upload", icon: Video, tone: "bg-chart-5/15 text-chart-5 border-chart-5/30" },
};

function normalizeType(t: string | undefined): SurveyType {
  if (t === "virtual" || t === "video") return t;
  return "physical";
}

import {
  useAllLeadsQuery,
  useApproveSurveyMutation,
  useProposeSurveyRescheduleMutation,
  useAssignSurveyorMutation,
  useStartVideoCallMutation,
  useSyncVideoCallStatusMutation,
  useSendAppCredentialsMutation,
  useTeamMembersQuery,
} from "@/lib/queries";
import { SurveyMediaGallery, type GallerySurveyInfo } from "@/components/SurveyMediaGallery";
import { SurveyReportView } from "@/components/SurveyReportView";

function SurveysPage() {
  const { data: rawLeads = [], isLoading: loading, refetch, isRefetching } = useAllLeadsQuery();
  const approveSurveyMutation = useApproveSurveyMutation();
  const proposeRescheduleMutation = useProposeSurveyRescheduleMutation();
  const assignSurveyorMutation = useAssignSurveyorMutation();
  const startVideoCallMutation = useStartVideoCallMutation();
  const syncVideoCallStatusMutation = useSyncVideoCallStatusMutation();
  const sendAppCredentialsMutation = useSendAppCredentialsMutation();

  const [videoCallOpen, setVideoCallOpen] = useState(false);
  const [videoUploadOpen, setVideoUploadOpen] = useState(false);
  const [mediaGallerySurvey, setMediaGallerySurvey] = useState<GallerySurveyInfo | null>(null);
  const [activeRoomUrl, setActiveRoomUrl] = useState<string>("");
  const [activeRoomCode, setActiveRoomCode] = useState<string>("");
  const [activeLeadId, setActiveLeadId] = useState<string | undefined>(undefined);
  const [activeLeadName, setActiveLeadName] = useState<string | undefined>(undefined);
  const [activeLeadNotes, setActiveLeadNotes] = useState<string>("");
  const [editingNotesSurvey, setEditingNotesSurvey] = useState<any | null>(null);
  const [editNotesText, setEditNotesText] = useState<string>("");
  const [isSavingNotesModal, setIsSavingNotesModal] = useState<boolean>(false);

  const handleLaunchVideoCallForSurvey = async (leadId?: string, leadName?: string, existingNotes?: string) => {
    setActiveLeadNotes(existingNotes || "");
    try {
      if (leadId) {
        await startVideoCallMutation.mutateAsync(leadId);
      }
      const room = await createVideoCallRoom(leadId, leadName || "Virtual Survey");
      setActiveRoomUrl(room.roomUrl);
      setActiveRoomCode(room.roomCode);
      setActiveLeadId(leadId);
      setActiveLeadName(leadName);
      setVideoCallOpen(true);
    } catch (err: any) {
      console.warn("Call room launched with fallback:", err);
      setActiveRoomUrl(`https://vpaas-magic-cookie.8x8.vc/NextGen-Call-${leadId || "101"}`);
      setActiveRoomCode(`NextGen-Call-${leadId || "101"}`);
      setActiveLeadId(leadId);
      setActiveLeadName(leadName);
      setVideoCallOpen(true);
    }
  };

  const [activeCallSurvey, setActiveCallSurvey] = useState<Survey | null>(null);
  const [credentialsSurvey, setCredentialsSurvey] = useState<Survey | null>(null);
  const [credPassword, setCredPassword] = useState("");
  const [credMessage, setCredMessage] = useState("Please install the Next Gen app and login with these credentials.");

  // Poll outgoing video call status when activeCallSurvey is ringing
  useEffect(() => {
    if (!activeCallSurvey || !activeCallSurvey.leadId) return;

    let isMounted = true;
    const interval = setInterval(async () => {
      try {
        const data = await fetchVideoCallStatus(activeCallSurvey.leadId!);
        const status = data?.status || "idle";

        if (!isMounted) return;

        if (status === "in_progress") {
          toast.success("Call connected! Joining video room...");
          const surveyCopy = activeCallSurvey;
          setActiveCallSurvey(null);
          setActiveRoomCode(data.roomId || surveyCopy.videoCallRoomId || `ROOM-${surveyCopy.leadId}`);
          setActiveLeadId(surveyCopy.leadId);
          setActiveLeadName(surveyCopy.clientName);
          setActiveLeadNotes(surveyCopy.notes || "");
          setVideoCallOpen(true);
        } else if (["declined", "missed", "ended", "completed", "failed"].includes(status)) {
          if (status === "declined") toast.error("Call was declined by client.");
          else if (status === "missed") toast.info("No answer — call timed out (30s).");
          setActiveCallSurvey(null);
        }
      } catch (err) {
        // ignore polling errors
      }
    }, 2000);

    return () => {
      isMounted = false;
      clearInterval(interval);
    };
  }, [activeCallSurvey]);

  const [searchQuery, setSearchQuery] = useState("");
  const debouncedSearch = useDebouncedValue(searchQuery, 300);
  const [statusFilter, setStatusFilter] = useState<string>("all");

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 50;

  const [selectedSurvey, setSelectedSurvey] = useState<Survey | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const [assigningSurvey, setAssigningSurvey] = useState<Survey | null>(null);
  const { data: teamMembers = [], isLoading: loadingTeam } = useTeamMembersQuery();
  const [selectedSurveyorEmail, setSelectedSurveyorEmail] = useState("");
  const [currentTab, setCurrentTab] = useState<"ledger" | "allocations">("ledger");

  const [rescheduleSurveyModal, setRescheduleSurveyModal] = useState<Survey | null>(null);
  const [proposedDate, setProposedDate] = useState("");
  const [proposedTime, setProposedTime] = useState("");
  
  // Dropdown open ID
  const [openDropdownId, setOpenDropdownId] = useState<string | null>(null);

  useEffect(() => {
    const closeAll = () => setOpenDropdownId(null);
    window.addEventListener("click", closeAll);
    return () => window.removeEventListener("click", closeAll);
  }, []);

  const handleProposeRescheduleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rescheduleSurveyModal || !rescheduleSurveyModal.leadId) return;

    setBusyId(`reschedule-${rescheduleSurveyModal.id}`);
    try {
      await proposeRescheduleMutation.mutateAsync({
        id: rescheduleSurveyModal.leadId,
        proposedDate,
        proposedTime,
      });
      setRescheduleSurveyModal(null);
    } catch (err: any) {
      toast.error(err.message || "Failed to send reschedule proposal to client");
    } finally {
      setBusyId(null);
    }
  };

  const surveys: Survey[] = useMemo(() => {
    const leads = Array.isArray(rawLeads) ? rawLeads : [];
    return leads
      .filter(
        (l: any) =>
          l.status !== "draft" &&
          (l.surveyBookedAt ||
            l.surveyRequestedDate ||
            l.surveyStatus === "booked" ||
            l.stage === "Survey" ||
            l.reminderContext === "survey")
      )
      .map((l: any) => ({
        id: l.id,
        surveyNumber: `SRV-${l.id}`,
        clientName: l.name,
        clientEmail: l.email,
        clientPhone: l.phone,
        surveyDate: l.surveyRequestedDate || (l.surveyBookedAt ? l.surveyBookedAt.split("T")[0] : "TBD"),
        surveyTime: l.surveyRequestedTimeRange || "TBD",
        surveyType: normalizeType(l.surveyType),
        propertyType: l.moveType,
        location: `${l.from || "—"} → ${l.to || "—"}`,
        status: l.surveyApprovedAt
          ? "Approved"
          : l.surveyBookedAt || l.surveyStatus === "booked"
          ? "Scheduled"
          : "Pending",
        notes: l.surveyNotes || "Customer scheduled via the survey-suggestion email.",
        leadId: l.id,
        surveyorName: l.surveyorName,
        surveyorEmail: l.surveyorEmail,
        surveyorReportNotes: l.surveyorReportNotes,
        surveyorMedia: Array.isArray(l.surveyorMedia) ? l.surveyorMedia : [],
        surveyorCompletedAt: l.surveyorCompletedAt,
        // ── Structured Survey Report Fields (Section C–G) ──
        cargoVolume: l.cargoVolume,
        totalBoxes: l.totalBoxes,
        recommendedVehicle: l.recommendedVehicle,
        packingService: l.packingService,
        accessOrigin: l.accessOrigin,
        liftAvailable: l.liftAvailable,
        parkingAvailable: l.parkingAvailable,
        customAccessFields: l.customAccessFields,
        requiresDisassembly: l.requiresDisassembly,
        bedsQuantity: l.bedsQuantity,
        wardrobesQuantity: l.wardrobesQuantity,
        customDismantleItems: l.customDismantleItems,
        hasFragileItems: l.hasFragileItems,
        fineArtPaintings: l.fineArtPaintings,
        pianoAntiqueItems: l.pianoAntiqueItems,
        customFragileFields: l.customFragileFields,
        specialInstructions: l.specialInstructions,
        surveyorFindings: l.surveyorFindings,
        videoCallRoomId: l.videoCallRoomId,
        videoCallStatus: l.videoCallStatus,
        videoCallToken: l.videoCallToken,
        appUserPassword: l.appUserPassword,
        credentialsSentAt: l.credentialsSentAt,
        rescheduleProposedDate: l.rescheduleProposedDate,
        rescheduleProposedTime: l.rescheduleProposedTime,
        rescheduleStatus: l.rescheduleStatus,
        rescheduleToken: l.rescheduleToken,
      }));
  }, [rawLeads]);

  const handleApprove = async (s: Survey) => {
    if (!s.leadId) return;
    setBusyId(s.id);
    try {
      await approveSurveyMutation.mutateAsync(s.leadId);
    } catch (err) {
      console.error("Failed to approve survey:", err);
    } finally {
      setBusyId(null);
    }
  };

  const handleSaveAssignSurveyor = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!assigningSurvey || !assigningSurvey.leadId) return;

    const surveyorUser = teamMembers.find((m) => m.email === selectedSurveyorEmail);
    const surveyorName = surveyorUser ? surveyorUser.name : selectedSurveyorEmail;

    setBusyId(assigningSurvey.id);
    try {
      await assignSurveyorMutation.mutateAsync({
        id: assigningSurvey.leadId,
        surveyorName,
        surveyorEmail: selectedSurveyorEmail,
      });
      setAssigningSurvey(null);
    } catch (err) {
      console.error("Failed to assign surveyor:", err);
    } finally {
      setBusyId(null);
    }
  };

  const filteredSurveys = useMemo(() => {
    const q = debouncedSearch.toLowerCase();
    return surveys.filter((s) => {
      const matchesSearch =
        String(s.clientName || "").toLowerCase().includes(q) ||
        String(s.surveyNumber || "").toLowerCase().includes(q) ||
        String(s.location || "").toLowerCase().includes(q);
      const matchesStatus = statusFilter === "all" || String(s.status || "").toLowerCase() === statusFilter.toLowerCase();
      return matchesSearch && matchesStatus;
    });
  }, [surveys, debouncedSearch, statusFilter]);

  // Reset page to 1 when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [debouncedSearch, statusFilter]);

  const totalPages = Math.ceil(filteredSurveys.length / itemsPerPage);
  const paginatedSurveys = filteredSurveys.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
              <ClipboardCheck className="h-3.5 w-3.5" /> Pre-Move Assessment
            </div>
            <h1 className="mt-1 font-display text-4xl font-semibold">Surveys</h1>
            <p className="text-sm text-muted-foreground">
              Customers schedule surveys (physical, virtual or video upload) from the emailed link. Approve each scheduled survey below.
            </p>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <button
              onClick={() => handleLaunchVideoCallForSurvey()}
              className="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-3.5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
            >
              <Video className="h-4 w-4" /> Live Video Survey Call
            </button>
            <button
              onClick={() => setVideoUploadOpen(true)}
              className="flex items-center gap-1.5 rounded-xl border border-gold/40 bg-gold/10 px-3.5 py-2 text-xs font-semibold text-gold hover:bg-gold/20 transition-all"
            >
              <Monitor className="h-4 w-4" /> Upload Survey Video
            </button>
          </div>
        </div>
      </div>

      {/* Metric Cards */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Total Surveys</span>
            <ClipboardCheck className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">{surveys.length}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Pre-move assessments recorded</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Scheduled</span>
            <Clock className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-gold">
            {surveys.filter((s) => s.status === "Scheduled").length}
          </div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Upcoming surveyor visits</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Approved</span>
            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-success">
            {surveys.filter((s) => s.status === "Approved").length}
          </div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Confirmed by admin</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Pending Allocation</span>
            <Calendar className="h-3.5 w-3.5 text-warning" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-warning">
            {surveys.filter((s) => s.status === "Pending").length}
          </div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Awaiting date confirmation</div>
        </GlassCard>
      </div>

      {/* Tab Switcher */}
      <div className="flex border-b border-border/40 gap-6">
        <button
          onClick={() => setCurrentTab("ledger")}
          className={`pb-3 text-sm font-semibold border-b-2 transition-all ${
            currentTab === "ledger"
              ? "border-gold text-gold"
              : "border-transparent text-muted-foreground hover:text-foreground"
          }`}
        >
          Surveys Ledger
        </button>
        <button
          onClick={() => setCurrentTab("allocations")}
          className={`pb-3 text-sm font-semibold border-b-2 transition-all ${
            currentTab === "allocations"
              ? "border-gold text-gold"
              : "border-transparent text-muted-foreground hover:text-foreground"
          }`}
        >
          Surveyor Allocations & Progress
        </button>
      </div>

      {currentTab === "ledger" ? (
        <>
          {/* Search & Filter */}
          <GlassCard className="p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by survey number, client, or location..."
            className="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
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

        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-muted-foreground" />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
          >
            <option value="all" className="bg-card">All Statuses</option>
            <option value="scheduled" className="bg-card">Scheduled</option>
            <option value="approved" className="bg-card">Approved</option>
            <option value="pending" className="bg-card">Pending</option>
          </select>
        </div>
      </GlassCard>

      {/* Table */}
      <GlassCard className="overflow-hidden">
        <div className="border-b border-border/60 p-5 flex items-center justify-between">
          <h2 className="font-display text-xl font-semibold">Surveys Ledger</h2>
          <span className="text-xs text-muted-foreground">{filteredSurveys.length} surveys listed</span>
        </div>

        <div className="overflow-x-auto min-h-[350px]">
          <table className="w-full text-left text-xs md:text-sm">
            <thead className="bg-muted/30 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
              <tr>
                <th className="px-3 py-3">Survey Ref</th>
                <th className="px-3 py-3">Client</th>
                <th className="px-3 py-3">Property</th>
                <th className="px-3 py-3">Location</th>
                <th className="px-3 py-3">Date & Time</th>
                <th className="px-3 py-3">Type</th>
                <th className="px-3 py-3">Assigned Surveyor</th>
                <th className="px-3 py-3">Status</th>
                <th className="px-3 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/40">
              {paginatedSurveys.map((s) => (
                <tr key={s.id} className="hover:bg-sidebar-accent/50 transition-colors">
                  <td className="px-3 py-3.5 font-mono font-semibold text-gold">{s.surveyNumber}</td>
                  <td className="px-3 py-3.5">
                    <div className="font-semibold text-foreground">{s.clientName}</div>
                    <div className="text-[10px] text-muted-foreground max-w-[130px] truncate" title={s.clientEmail}>{s.clientEmail}</div>
                  </td>
                  <td className="px-3 py-3.5 text-muted-foreground text-xs">{s.propertyType}</td>
                  <td className="px-3 py-3.5 text-xs max-w-[120px] truncate" title={s.location}>{s.location}</td>
                  <td className="px-3 py-3.5 text-xs whitespace-normal max-w-[130px]">
                    <div className="font-medium text-foreground">{s.surveyDate}</div>
                    <div className="text-[10px] text-muted-foreground mt-0.5">{s.surveyTime}</div>
                  </td>
                  <td className="px-3 py-3.5">
                    {(() => {
                      const meta = surveyTypeMeta[s.surveyType];
                      const Icon = meta.icon;
                      return (
                        <span className={`inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium border ${meta.tone} whitespace-nowrap`}>
                          <Icon className="h-3 w-3" /> {meta.label}
                        </span>
                      );
                    })()}
                  </td>
                  <td className="px-3 py-3.5 text-xs">
                    {s.surveyorName ? (
                      <span className="font-medium text-gold whitespace-nowrap">{s.surveyorName}</span>
                    ) : (
                      <button
                        onClick={() => {
                          setAssigningSurvey(s);
                          setSelectedSurveyorEmail(s.surveyorEmail || "");
                        }}
                        className="text-muted-foreground hover:text-gold underline text-[11px] whitespace-nowrap"
                      >
                        + Assign Surveyor
                      </button>
                    )}
                  </td>
                  <td className="px-3 py-3.5">
                    <div className="space-y-0.5">
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${
                          s.status === "Approved"
                            ? "bg-success/15 text-success border border-success/30"
                            : s.rescheduleStatus === "client_approved"
                            ? "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"
                            : s.rescheduleStatus === "proposed"
                            ? "bg-amber-500/15 text-amber-400 border border-amber-500/30"
                            : s.status === "Scheduled"
                            ? "bg-gold/15 text-gold border border-gold/30"
                            : "bg-warning/15 text-warning border border-warning/30"
                        }`}
                      >
                        {s.status === "Approved"
                          ? "Approved"
                          : s.rescheduleStatus === "client_approved"
                          ? "Client Approved"
                          : s.rescheduleStatus === "proposed"
                          ? "Proposed"
                          : s.status}
                      </span>
                      {s.rescheduleStatus === "proposed" && (
                        <div className="text-[9px] text-amber-400/80 font-mono whitespace-nowrap">
                          {s.rescheduleProposedDate} @ {s.rescheduleProposedTime}
                        </div>
                      )}
                    </div>
                  </td>
                  <td className="px-3 py-3.5 text-right">
                    <div className="flex items-center justify-end gap-1 relative">
                      {/* Primary Action Button (Single Click) */}
                      {s.status === "Scheduled" && s.leadId ? (
                        <button
                          disabled={busyId === s.id}
                          onClick={() => handleApprove(s)}
                          className="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-gold-bright to-gold-dim px-2.5 py-1 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50 transition-all whitespace-nowrap"
                        >
                          <Check className="h-3 w-3" /> Approve
                        </button>
                      ) : s.status === "Approved" && s.leadId ? (
                        <button
                          onClick={() => {
                            setCredentialsSurvey(s);
                            setCredPassword(s.appUserPassword || `NG-${Math.floor(100000 + Math.random() * 900000)}`);
                            setCredMessage("Please install the Next Gen app and login with these credentials.");
                          }}
                          className="inline-flex items-center gap-1 rounded-lg border border-gold/40 bg-gold/15 px-2.5 py-1 text-xs font-semibold text-gold hover:bg-gold/25 transition-all whitespace-nowrap"
                        >
                          <Key className="h-3 w-3" /> Credentials
                        </button>
                      ) : (
                        <button
                          onClick={() => setSelectedSurvey(s)}
                          className="inline-flex items-center gap-1 rounded-lg border border-border bg-card/60 px-2.5 py-1 text-xs text-muted-foreground hover:border-gold/50 hover:text-gold transition-colors whitespace-nowrap"
                        >
                          <Eye className="h-3 w-3" /> View
                        </button>
                      )}

                      {/* Dropdown Action Menu Trigger */}
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          setOpenDropdownId(openDropdownId === s.id ? null : s.id);
                        }}
                        className="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-card/40 hover:border-gold/50 hover:text-gold transition-all text-muted-foreground"
                        title="More actions"
                      >
                        <MoreHorizontal className="h-4 w-4" />
                      </button>

                      {/* Floating Dropdown Menu Options */}
                      {openDropdownId === s.id && (
                        <div className="absolute right-0 top-full mt-1.5 z-40 w-44 rounded-xl border border-border/80 bg-[#121319]/95 backdrop-blur-md p-1 shadow-2xl animate-in fade-in slide-in-from-top-1 duration-100 text-left">
                          <button
                            onClick={() => setSelectedSurvey(s)}
                            className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-card/60 transition-colors"
                          >
                            <Eye className="h-3.5 w-3.5" /> View Details
                          </button>
                          
                          <button
                            onClick={() => {
                              setAssigningSurvey(s);
                              setSelectedSurveyorEmail(s.surveyorEmail || "");
                            }}
                            className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-card/60 transition-colors"
                          >
                            <ClipboardCheck className="h-3.5 w-3.5" /> Assign Surveyor
                          </button>

                          {s.status === "Scheduled" && s.leadId && (
                            <button
                              onClick={() => {
                                setRescheduleSurveyModal(s);
                                setProposedDate(s.surveyDate !== "TBD" ? s.surveyDate : new Date().toISOString().split("T")[0]);
                                setProposedTime(s.surveyTime !== "TBD" ? s.surveyTime : "10:30 AM");
                              }}
                              className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-amber-400/90 hover:text-amber-400 hover:bg-amber-500/10 transition-colors"
                            >
                              <Clock className="h-3.5 w-3.5" /> Reschedule Survey
                            </button>
                          )}

                          {s.surveyType === "virtual" && s.leadId && (
                            <button
                              disabled={busyId === `call-${s.id}`}
                              onClick={async () => {
                                try {
                                  const res = await startVideoCallMutation.mutateAsync(s.leadId!);
                                  setActiveCallSurvey({
                                    ...s,
                                    videoCallRoomId: res.call?.roomId || `ROOM-${s.leadId}`,
                                    videoCallStatus: "ringing",
                                    videoCallToken: res.call?.clientToken,
                                  });
                                } catch (err) {
                                  console.error("Failed to start call:", err);
                                }
                              }}
                              className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-chart-3 hover:text-chart-3 hover:bg-chart-3/15 transition-colors"
                            >
                              <Video className="h-3.5 w-3.5" /> Call Client (Video)
                            </button>
                          )}

                          {s.status === "Approved" && s.leadId && (
                            <button
                              onClick={() => {
                                setCredentialsSurvey(s);
                                setCredPassword(s.appUserPassword || `NG-${Math.floor(100000 + Math.random() * 900000)}`);
                                setCredMessage("Please install the Next Gen app and login with these credentials.");
                              }}
                              className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-gold hover:text-gold hover:bg-gold/15 transition-colors"
                            >
                              <Key className="h-3.5 w-3.5" /> App Credentials
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {paginatedSurveys.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-4 py-12 text-center text-sm text-muted-foreground">
                    {loading ? "Loading surveys…" : "No surveys scheduled yet. They appear here once a customer books via the emailed link."}
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
              Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredSurveys.length)} of {filteredSurveys.length} surveys
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="h-8 px-3 text-xs rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 disabled:opacity-50"
              >
                Previous
              </button>
              <div className="text-sm text-white/70 px-4">
                Page {currentPage} of {totalPages}
              </div>
              <button
                onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="h-8 px-3 text-xs rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </GlassCard>
      </>
      ) : (
        <div className="space-y-6 animate-in fade-in duration-200">
          {(() => {
            const groups: Record<string, { name: string; email: string; items: Survey[] }> = {};
            
            surveys.forEach((s) => {
              const email = s.surveyorEmail || "unassigned";
              const name = s.surveyorName || "Unassigned Surveys";
              if (!groups[email]) {
                groups[email] = { name, email, items: [] };
              }
              groups[email].items.push(s);
            });

            const groupList = Object.values(groups);

            if (groupList.length === 0) {
              return (
                <div className="p-8 text-center text-muted-foreground border border-dashed border-border/40 rounded-2xl bg-card/25">
                  No surveyor allocations found.
                </div>
              );
            }

            return (
              <div className="grid gap-6 grid-cols-1">
                {groupList.map((g) => (
                  <GlassCard key={g.email} className="p-6 border border-border/50 bg-[#0F1017]">
                    <div className="flex items-center justify-between border-b border-border/40 pb-4 mb-4">
                      <div>
                        <h3 className="font-display text-lg font-semibold text-foreground flex items-center gap-2">
                          <Users className="h-5 w-5 text-gold" /> {g.name}
                        </h3>
                        {g.email !== "unassigned" && (
                          <p className="text-xs text-muted-foreground mt-0.5 font-mono">{g.email}</p>
                        )}
                      </div>
                      <span className="rounded-full bg-gold/10 px-3 py-1 text-xs font-semibold text-gold border border-gold/30">
                        {g.items.length} Client{g.items.length !== 1 ? "s" : ""} Linked
                      </span>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs md:text-sm">
                        <thead className="bg-muted/20 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/30">
                          <tr>
                            <th className="px-3 py-2">Client Details</th>
                            <th className="px-3 py-2">Ref / Type</th>
                            <th className="px-3 py-2">Scheduled Date & Time</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">What They Done</th>
                            <th className="px-3 py-2">What Is Pending</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border/30">
                          {g.items.map((s) => {
                            const doneItems = [];
                            const pendingItems = [];

                            if (s.surveyorReportNotes) {
                              doneItems.push("Report notes filed");
                            } else {
                              pendingItems.push("Write assessment report notes");
                            }

                            if (s.surveyorMedia && s.surveyorMedia.length > 0) {
                              doneItems.push(`Media uploaded (${s.surveyorMedia.length} item${s.surveyorMedia.length !== 1 ? "s" : ""})`);
                            } else {
                              pendingItems.push("Upload survey photos / video");
                            }

                            if (s.status === "Approved") {
                              doneItems.push("Admin approved & finalized");
                            } else {
                              pendingItems.push("Awaiting admin review & approval");
                            }

                            return (
                              <tr key={s.id} className="hover:bg-sidebar-accent/30 transition-colors">
                                <td className="px-3 py-3.5">
                                  <div className="font-semibold text-foreground">{s.clientName}</div>
                                  <div className="text-[10px] text-muted-foreground">{s.clientEmail}</div>
                                </td>
                                <td className="px-3 py-3.5">
                                  <span className="font-mono text-gold text-xs">{s.surveyNumber}</span>
                                </td>
                                <td className="px-3 py-3.5 text-xs">
                                  <div>{s.surveyDate}</div>
                                  <div className="text-muted-foreground text-[10px]">{s.surveyTime}</div>
                                </td>
                                <td className="px-3 py-3.5">
                                  <span className="rounded-full bg-gold/10 px-2 py-0.5 text-[10px] text-gold border border-gold/30">
                                    {s.status}
                                  </span>
                                </td>
                                <td className="px-3 py-3.5 text-xs text-success">
                                  {doneItems.length > 0 ? doneItems.join(", ") : "—"}
                                </td>
                                <td className="px-3 py-3.5 text-xs text-warning">
                                  {pendingItems.length > 0 ? pendingItems.join(", ") : "—"}
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </GlassCard>
                ))}
              </div>
            );
          })()}
        </div>
      )}

      {/* Assign Surveyor Modal */}
      {assigningSurvey && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150 border border-gold/30 bg-[#0F1017]">
            <button
              onClick={() => setAssigningSurvey(null)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 mb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold font-mono font-bold text-sm">
                <Users className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Assign Surveyor</h2>
                <p className="text-xs text-gold font-semibold">{assigningSurvey.clientName} ({assigningSurvey.surveyNumber})</p>
              </div>
            </div>

            <form onSubmit={handleSaveAssignSurveyor} className="space-y-4 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">
                  Select Team Member
                </label>
                <select
                  value={selectedSurveyorEmail}
                  onChange={(e) => setSelectedSurveyorEmail(e.target.value)}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                  required
                >
                  <option value="" disabled>-- Select Surveyor --</option>
                  {teamMembers.map((member: any) => (
                    <option key={member.id} value={member.email}>
                      {member.name} ({member.email})
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setAssigningSurvey(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={busyId === assigningSurvey.id}
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground disabled:opacity-50"
                >
                  {busyId === assigningSurvey.id ? "Saving…" : "Confirm Assignment"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Outgoing Live Video Call Modal */}
      {activeCallSurvey && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative border border-chart-3/50 shadow-2xl animate-in zoom-in-95 duration-150">
            <button
              onClick={async () => {
                if (activeCallSurvey.leadId) {
                  await syncVideoCallStatusMutation.mutateAsync({ leadId: activeCallSurvey.leadId, status: "ended" });
                }
                setActiveCallSurvey(null);
              }}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="text-center space-y-4">
              <div className="relative inline-flex items-center justify-center">
                <div className="absolute inset-0 rounded-full bg-chart-3/30 animate-ping"></div>
                <div className="relative h-20 w-20 rounded-full bg-chart-3/20 border-2 border-chart-3 flex items-center justify-center text-chart-3">
                  <Video className="h-10 w-10 animate-pulse" />
                </div>
              </div>

              <div>
                <span className="inline-block rounded-full bg-chart-3/20 px-3 py-1 text-xs font-semibold text-chart-3 border border-chart-3/40 mb-2">
                  Outgoing Video Call Survey
                </span>
                <h2 className="font-display text-2xl font-semibold text-foreground">
                  Calling {activeCallSurvey.clientName}...
                </h2>
                <p className="text-xs text-muted-foreground mt-1">
                  Client receives incoming call pickup alert on their Mobile App
                </p>
              </div>

              <div className="rounded-xl bg-card/60 p-4 border border-border/50 text-left space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Survey Ref:</span>
                  <span className="font-mono text-gold">{activeCallSurvey.surveyNumber}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Call Room ID:</span>
                  <span className="font-mono text-foreground">{activeCallSurvey.videoCallRoomId}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Client Phone:</span>
                  <span className="text-foreground">{activeCallSurvey.clientPhone || "N/A"}</span>
                </div>
                <div className="flex justify-between items-center pt-2 border-t border-border/40">
                  <span className="text-muted-foreground">Call Status:</span>
                  <span className="uppercase font-bold text-chart-3 tracking-wide flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full bg-chart-3 animate-ping"></span> Ringing App Client...
                  </span>
                </div>
              </div>

              <div className="p-3 bg-muted/20 rounded-xl text-[11px] text-muted-foreground italic border border-border/30">
                💡 Mobile App Integration API active: <code className="text-gold font-mono">/api/customer/video-call/verify</code>
              </div>

              <div className="flex items-center justify-center gap-3 pt-2">
                <button
                  onClick={() => {
                    const surveyCopy = activeCallSurvey;
                    setActiveCallSurvey(null);
                    if (surveyCopy) {
                      setActiveRoomCode(surveyCopy.videoCallRoomId || `ROOM-${surveyCopy.leadId}`);
                      setActiveLeadId(surveyCopy.leadId);
                      setActiveLeadName(surveyCopy.clientName);
                      setActiveLeadNotes(surveyCopy.notes || "");
                      setVideoCallOpen(true);
                    }
                  }}
                  className="w-1/2 rounded-xl bg-chart-3 hover:bg-chart-3/90 py-3 text-xs font-bold text-white transition-all shadow-lg flex items-center justify-center gap-2"
                >
                  <Video className="h-4 w-4" /> Join Room Now
                </button>
                <button
                  onClick={async () => {
                    if (activeCallSurvey.leadId) {
                      await syncVideoCallStatusMutation.mutateAsync({ leadId: activeCallSurvey.leadId, status: "ended" });
                    }
                    setActiveCallSurvey(null);
                  }}
                  className="w-1/2 rounded-xl bg-red-600 hover:bg-red-700 py-3 text-xs font-bold text-white transition-all shadow-lg flex items-center justify-center gap-2"
                >
                  <X className="h-4 w-4" /> End Call
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Send / Edit Mobile App Credentials Modal */}
      {credentialsSurvey && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative border border-gold/40 shadow-2xl animate-in zoom-in-95 duration-150">
            <button
              onClick={() => setCredentialsSurvey(null)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 mb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold">
                <Key className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Mobile App User Account</h2>
                <p className="text-xs text-gold">{credentialsSurvey.clientName} ({credentialsSurvey.clientEmail})</p>
              </div>
            </div>

            <form
              onSubmit={async (e) => {
                e.preventDefault();
                if (!credentialsSurvey.leadId) return;
                setBusyId(`cred-${credentialsSurvey.id}`);
                try {
                  await sendAppCredentialsMutation.mutateAsync({
                    leadId: credentialsSurvey.leadId,
                    password: credPassword,
                    message: credMessage,
                  });
                  setCredentialsSurvey(null);
                } catch (err) {
                  console.error("Failed to send credentials:", err);
                } finally {
                  setBusyId(null);
                }
              }}
              className="space-y-4 text-xs"
            >
              <div>
                <label className="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">
                  User Email (Login Username)
                </label>
                <input
                  type="email"
                  disabled
                  value={credentialsSurvey.clientEmail}
                  className="w-full rounded-xl border border-border bg-input/20 px-3 py-2 text-muted-foreground outline-none cursor-not-allowed"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">
                  Generated Password (Editable)
                </label>
                <input
                  type="text"
                  required
                  value={credPassword}
                  onChange={(e) => setCredPassword(e.target.value)}
                  className="w-full rounded-xl border border-gold/40 bg-input/40 px-3 py-2 text-foreground font-mono outline-none focus:border-gold/70"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">
                  Custom Instruction Message
                </label>
                <textarea
                  rows={3}
                  value={credMessage}
                  onChange={(e) => setCredMessage(e.target.value)}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                  placeholder="Please install the Next Gen app and login with these credentials."
                />
              </div>

              <div className="rounded-xl bg-card/60 p-3 border border-border/40 text-[11px] text-muted-foreground space-y-1">
                <div className="font-semibold text-gold">Email Delivery via Brevo SMTP:</div>
                <div>An automated email with these credentials, login instructions, and API Server URL will be sent to <strong>{credentialsSurvey.clientEmail}</strong>.</div>
                {credentialsSurvey.credentialsSentAt && (
                  <div className="text-success pt-1">
                    ✓ Credentials previously sent on {new Date(credentialsSurvey.credentialsSentAt).toLocaleString("en-GB")}
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setCredentialsSurvey(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={busyId === `cred-${credentialsSurvey.id}`}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground disabled:opacity-50"
                >
                  <Send className="h-3.5 w-3.5" />
                  {busyId === `cred-${credentialsSurvey.id}` ? "Sending…" : "Send Credentials Email"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Propose Reschedule Modal */}
      {rescheduleSurveyModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150 border border-amber-500/40 bg-[#0F1017]">
            <button
              onClick={() => setRescheduleSurveyModal(null)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 mb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400 font-mono font-bold text-sm border border-amber-500/30">
                <Clock className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Propose Survey Reschedule</h2>
                <p className="text-xs text-amber-400 font-semibold">{rescheduleSurveyModal.clientName} ({rescheduleSurveyModal.surveyNumber})</p>
              </div>
            </div>

            <form onSubmit={handleProposeRescheduleSubmit} className="space-y-4 text-xs">
              <div className="rounded-xl bg-card/60 p-3 border border-border/40 space-y-1">
                <div className="text-[11px] font-semibold text-muted-foreground uppercase">Current Requested Schedule:</div>
                <div className="text-foreground font-medium">
                  {rescheduleSurveyModal.surveyDate} at {rescheduleSurveyModal.surveyTime}
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-bold uppercase tracking-wider text-amber-400 mb-1">
                  Proposed New Date
                </label>
                <input
                  type="date"
                  required
                  value={proposedDate}
                  onChange={(e) => setProposedDate(e.target.value)}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-amber-500/70"
                />
              </div>

              <div>
                <label className="block text-[11px] font-bold uppercase tracking-wider text-amber-400 mb-1">
                  Proposed Exact Time (e.g. 10:30 AM / 14:00)
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 10:30 AM"
                  value={proposedTime}
                  onChange={(e) => setProposedTime(e.target.value)}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-amber-500/70"
                />
              </div>

              <div className="rounded-xl bg-amber-500/10 p-3 border border-amber-500/20 text-[11px] text-amber-300/90 space-y-1">
                <div className="font-semibold text-amber-400">Client Approval Email Workflow:</div>
                <div>
                  An email will be sent to <strong>{rescheduleSurveyModal.clientEmail}</strong> with your proposed date & exact time. The email contains a link for the client to confirm the new schedule. Once confirmed, you will be notified to approve the survey.
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setRescheduleSurveyModal(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={busyId === `reschedule-${rescheduleSurveyModal.id}`}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 text-black font-bold px-5 py-2 text-xs hover:bg-amber-400 disabled:opacity-50 transition-all shadow-md"
                >
                  <Send className="h-3.5 w-3.5" />
                  {busyId === `reschedule-${rescheduleSurveyModal.id}` ? "Sending Proposal…" : "Send Proposal to Client"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Live HD Video Call Modal */}
      <VideoCallModal
        open={videoCallOpen}
        onClose={() => setVideoCallOpen(false)}
        roomUrl={activeRoomUrl}
        roomCode={activeRoomCode}
        leadId={activeLeadId}
        leadName={activeLeadName}
        initialNotes={activeLeadNotes}
        onNotesSaved={() => refetch()}
      />

      {/* Video Survey Upload Modal */}
      <VideoUploadModal
        open={videoUploadOpen}
        onClose={() => setVideoUploadOpen(false)}
        leadId={activeLeadId}
        leadName={activeLeadName}
      />
      {/* Survey Media Gallery (Mobile App Data) */}
      {mediaGallerySurvey && (
        <SurveyMediaGallery
          survey={mediaGallerySurvey}
          onClose={() => setMediaGallerySurvey(null)}
          readOnly={true}
        />
      )}

      {/* Edit / Recall Video Call Notes Modal */}
      {editingNotesSurvey && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-xl rounded-2xl p-6 relative border border-gold/40 shadow-2xl animate-in zoom-in-95 duration-150 space-y-4">
            <button
              onClick={() => setEditingNotesSurvey(null)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground"
            >
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3">
              <div className="p-2.5 rounded-xl bg-gold/15 border border-gold/30 text-gold">
                <Video className="h-5 w-5" />
              </div>
              <div>
                <h3 className="font-display text-lg font-bold text-foreground">Video Call & Survey Notes</h3>
                <p className="text-xs text-muted-foreground">
                  Client: <strong className="text-gold">{editingNotesSurvey.clientName}</strong> ({editingNotesSurvey.surveyNumber})
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-xs font-semibold uppercase tracking-wider text-gold block">
                Recorded Video Call Notes
              </label>
              <textarea
                value={editNotesText}
                onChange={(e) => setEditNotesText(e.target.value)}
                placeholder="Type or update video call notes, inventory items, fragile warnings, etc..."
                rows={6}
                className="w-full rounded-xl border border-border bg-input/40 p-3.5 text-xs text-foreground outline-none focus:border-gold/60 resize-none font-sans"
              />
            </div>

            <div className="flex items-center justify-between pt-2 border-t border-border/60">
              <span className="text-[11px] text-muted-foreground">
                Notes are synced to Lead history & survey reports.
              </span>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setEditingNotesSurvey(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={isSavingNotesModal}
                  onClick={async () => {
                    if (!editingNotesSurvey.leadId && !editingNotesSurvey.id) return;
                    const leadId = editingNotesSurvey.leadId || editingNotesSurvey.id;
                    setIsSavingNotesModal(true);
                    try {
                      await fetch(`/api/leads/${leadId}/video-call/notes`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ notes: editNotesText }),
                      });
                      await refetch();
                      setEditingNotesSurvey(null);
                      toast.success("Video call notes updated successfully!");
                    } catch (err) {
                      console.error("Failed to save notes:", err);
                      toast.error("Failed to save notes");
                    } finally {
                      setIsSavingNotesModal(false);
                    }
                  }}
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-bold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50"
                >
                  {isSavingNotesModal ? "Saving Notes…" : "Save Notes"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
