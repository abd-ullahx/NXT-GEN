import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState, useCallback } from "react";
import {
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  format,
  isSameMonth,
  isSameDay,
  addMonths,
} from "date-fns";
import {
  ClipboardCheck,
  Search,
  Calendar,
  Clock,
  MapPin,
  FileText,
  Upload,
  Image as ImageIcon,
  Video,
  CheckCircle2,
  X,
  Phone,
  Mail,
  Monitor,
  Play,
  StickyNote,
  Trash2,
  ChevronLeft,
  ChevronRight,
  ZoomIn,
  RefreshCw,
  Camera,
  Layers,
  User,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useAuth } from "@/lib/auth";
import {
  fetchLeads,
  submitSurveyReport,
  createVideoCallRoom,
  fetchSurveyMedia,
  deleteSurveyMedia,
  type SurveyMediaItem,
} from "@/lib/api";
import { VideoCallModal } from "@/components/VideoCallModal";
import { VideoUploadModal } from "@/components/VideoUploadModal";

export const Route = createFileRoute("/app/surveyor")({
  head: () => ({ meta: [{ title: "Surveyor Portal — Next Gen Relocation CRM" }] }),
  validateSearch: (search: Record<string, unknown>) => {
    return {
      tab: search.tab as "pending" | "completed" | undefined,
      view: search.view as "list" | "calendar" | undefined,
    }
  },
  component: SurveyorPortalPage,
});

interface AssignedSurvey {
  id: string;
  surveyNumber: string;
  clientName: string;
  clientEmail: string;
  clientPhone: string;
  surveyDate: string;
  surveyTime: string;
  surveyType: string;
  moveType: string;
  from: string;
  to: string;
  location: string;
  status: string;
  notes: string;
  surveyorName?: string;
  surveyorEmail?: string;
  surveyorReportNotes?: string;
  surveyorMedia?: string[];
  surveyorCompletedAt?: string;
}

// Removed inline components; importing from shared component
import { SurveyMediaGallery, type GallerySurveyInfo } from "@/components/SurveyMediaGallery";
// ─── Main Page ────────────────────────────────────────────────────────────────
function SurveyorPortalPage() {
  const { user } = useAuth();
  const searchParams = Route.useSearch();
  const [assignedSurveys, setAssignedSurveys] = useState<AssignedSurvey[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"pending" | "completed">(searchParams.tab || "pending");
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedSurvey, setSelectedSurvey] = useState<AssignedSurvey | null>(null);
  const [mediaGallerySurvey, setMediaGallerySurvey] = useState<GallerySurveyInfo | null>(null);
  const [viewMode, setViewMode] = useState<"list" | "calendar">(searchParams.view || "list");
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [calendarCursor, setCalendarCursor] = useState<Date>(new Date());

  // Sync state when URL params change
  useEffect(() => {
    if (searchParams.tab) setActiveTab(searchParams.tab);
    if (searchParams.view) setViewMode(searchParams.view);
  }, [searchParams.tab, searchParams.view]);

  // Video Modals
  const [videoCallOpen, setVideoCallOpen] = useState(false);
  const [activeRoomUrl, setActiveRoomUrl] = useState("");
  const [activeRoomCode, setActiveRoomCode] = useState("");
  const [videoUploadOpen, setVideoUploadOpen] = useState(false);
  const [activeLeadId, setActiveLeadId] = useState("");
  const [activeLeadName, setActiveLeadName] = useState("");

  // Report Form State
  const [reportNotes, setReportNotes] = useState("");
  const [mediaUrls, setMediaUrls] = useState<string[]>([]);
  const [newMediaInput, setNewMediaInput] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const loadAssignedSurveys = async () => {
    setLoading(true);
    try {
      const leads = await fetchLeads();
      const all: AssignedSurvey[] = (Array.isArray(leads) ? leads : [])
        .filter(
          (l: any) =>
            l.surveyRequestedDate ||
            l.surveyBookedAt ||
            l.surveyorName ||
            l.surveyorEmail ||
            l.surveyStatus === "booked" ||
            l.stage === "Survey" ||
            l.status === "survey-booked"
        )
        .map((l: any) => ({
          id: l.id,
          surveyNumber: `SRV-${l.id}`,
          clientName: l.name,
          clientEmail: l.email,
          clientPhone: l.phone,
          surveyDate: l.surveyRequestedDate || (l.surveyBookedAt ? l.surveyBookedAt.split("T")[0] : "TBD"),
          surveyTime: l.surveyRequestedTimeRange || "10:00 AM",
          surveyType: l.surveyType || "physical",
          moveType: l.moveType || "Standard Move",
          from: l.from || "—",
          to: l.to || "—",
          location: `${l.from || "—"} → ${l.to || "—"}`,
          status: l.surveyorCompletedAt ? "Completed" : "Pending Inspection",
          notes: l.surveyNotes || "Survey booked via customer portal.",
          surveyorName: l.surveyorName || "Assigned Surveyor",
          surveyorEmail: l.surveyorEmail || "",
          surveyorReportNotes: l.surveyorReportNotes,
          surveyorMedia: Array.isArray(l.surveyorMedia) ? l.surveyorMedia : [],
          surveyorCompletedAt: l.surveyorCompletedAt,
        }));

      let filtered = all;
      if (user?.role === "surveyor" && user.email) {
        const uEmail = user.email.trim().toLowerCase();
        const uName = user.name?.trim().toLowerCase();
        const matches = all.filter(
          (s) =>
            (s.surveyorEmail && s.surveyorEmail.trim().toLowerCase() === uEmail) ||
            (s.surveyorName && uName && s.surveyorName.trim().toLowerCase() === uName)
        );
        filtered = matches;
      }

      setAssignedSurveys(filtered);
    } catch (err) {
      console.error("Failed to load surveyor assignments:", err);
      setAssignedSurveys([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadAssignedSurveys(); }, [user]);

  const handleLaunchVideoCall = async (survey: AssignedSurvey) => {
    try {
      const room = await createVideoCallRoom(survey.id, survey.clientName);
      setActiveRoomUrl(room.roomUrl);
      setActiveRoomCode(room.roomCode);
      setActiveLeadId(survey.id);
      setActiveLeadName(survey.clientName);
      setVideoCallOpen(true);
    } catch (err: any) {
      alert("Failed to launch video room: " + err.message);
    }
  };

  const handleOpenUploadModal = (survey: AssignedSurvey) => {
    setActiveLeadId(survey.id);
    setActiveLeadName(survey.clientName);
    setVideoUploadOpen(true);
  };

  const handleOpenReportModal = (survey: AssignedSurvey) => {
    setSelectedSurvey(survey);
    setReportNotes(survey.surveyorReportNotes || "");
    setMediaUrls(survey.surveyorMedia || []);
    setNewMediaInput("");
    setSuccessMessage(null);
  };

  const handleAddMedia = () => {
    if (!newMediaInput.trim()) return;
    setMediaUrls((prev) => [...prev, newMediaInput.trim()]);
    setNewMediaInput("");
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files) return;
    Array.from(files).forEach((file) => {
      const reader = new FileReader();
      reader.onload = (event) => {
        if (event.target?.result) {
          setMediaUrls((prev) => [...prev, event.target!.result as string]);
        }
      };
      reader.readAsDataURL(file);
    });
  };

  const handleRemoveMedia = (idx: number) => {
    setMediaUrls((prev) => prev.filter((_, i) => i !== idx));
  };

  const handleSubmitReport = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSurvey) return;
    setSubmitting(true);
    try {
      await submitSurveyReport(selectedSurvey.id, reportNotes, mediaUrls);
      setSuccessMessage("Survey report and inventory notes saved! Status updated to Completed.");
      setTimeout(() => {
        setSelectedSurvey(null);
        loadAssignedSurveys();
      }, 1500);
    } catch (err: any) {
      alert(err.message || "Failed to submit survey report");
    } finally {
      setSubmitting(false);
    }
  };

  const surveysOn = (d: Date) => {
    return assignedSurveys.filter((s) => {
      if (!s.surveyDate) return false;
      const [y, m, day] = s.surveyDate.split("-").map(Number);
      if (!y || !m || !day) return false;
      const surveyD = new Date(y, m - 1, day);
      return isSameDay(surveyD, d);
    });
  };

  const pendingList = assignedSurveys.filter((s) => s.status === "Pending Inspection");
  const completedList = assignedSurveys.filter((s) => s.status === "Completed");
  const currentTabSurveys = activeTab === "pending" ? pendingList : completedList;
  const filteredSurveysList = currentTabSurveys.filter(
    (s) =>
      s.clientName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      s.surveyNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
      s.location.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <ClipboardCheck className="h-3.5 w-3.5" /> Surveyor Workspace
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">
            Welcome back, {user?.name || "Surveyor"}
          </h1>
          <p className="text-sm text-muted-foreground">
            Dedicated workspace for your assigned property surveys & filed client inspections.
          </p>
        </div>
      </div>

      {/* Metric Cards */}
      <div className="grid gap-4 sm:grid-cols-3">
        <GlassCard hover className="p-5 border-gold/20 bg-[#0F1017]">
          <div className="flex items-center justify-between text-xs text-muted-foreground uppercase tracking-wider">
            <span>Total Assigned Surveys</span>
            <Calendar className="h-4 w-4 text-gold" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-foreground">{assignedSurveys.length}</div>
          <div className="mt-1 text-xs text-muted-foreground">Active property client visits</div>
        </GlassCard>

        <GlassCard hover className="p-5 border-gold/20 bg-[#0F1017]">
          <div className="flex items-center justify-between text-xs text-muted-foreground uppercase tracking-wider">
            <span>Pending Inspection</span>
            <Clock className="h-4 w-4 text-amber-400" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-amber-400">{pendingList.length}</div>
          <div className="mt-1 text-xs text-muted-foreground">Awaiting report filing</div>
        </GlassCard>

        <GlassCard hover className="p-5 border-gold/20 bg-[#0F1017]">
          <div className="flex items-center justify-between text-xs text-muted-foreground uppercase tracking-wider">
            <span>Completed Surveys</span>
            <CheckCircle2 className="h-4 w-4 text-emerald-400" />
          </div>
          <div className="mt-2 font-display text-3xl font-semibold text-emerald-400">{completedList.length}</div>
          <div className="mt-1 text-xs text-muted-foreground">Reports & walkthrough videos filed</div>
        </GlassCard>
      </div>

      {/* Main Roster */}
      <GlassCard className="overflow-hidden border-gold/20 bg-[#0F1017]">
        <div className="p-5 border-b border-border/60 flex flex-wrap items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-4">
            <div className="flex items-center bg-card/60 p-1 rounded-xl border border-border/60">
              <button
                onClick={() => setActiveTab("pending")}
                className={`px-4 py-2 rounded-lg text-xs font-semibold transition-all ${
                  activeTab === "pending"
                    ? "bg-gradient-to-r from-gold-bright to-gold-dim text-black shadow-md"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                Assigned Surveys ({pendingList.length})
              </button>
              <button
                onClick={() => setActiveTab("completed")}
                className={`px-4 py-2 rounded-lg text-xs font-semibold transition-all ${
                  activeTab === "completed"
                    ? "bg-emerald-500 text-black shadow-md"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                Completed Surveys ({completedList.length})
              </button>
            </div>

            <div className="flex items-center bg-card/60 p-1 rounded-xl border border-border/60">
              <button
                onClick={() => setViewMode("list")}
                className={`px-4 py-2 rounded-lg text-xs font-semibold transition-all ${
                  viewMode === "list"
                    ? "bg-gold/20 text-gold border border-gold/30 shadow-md"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                List View
              </button>
              <button
                onClick={() => setViewMode("calendar")}
                className={`px-4 py-2 rounded-lg text-xs font-semibold transition-all ${
                  viewMode === "calendar"
                    ? "bg-gold/20 text-gold border border-gold/30 shadow-md"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                Calendar Schedule
              </button>
            </div>
          </div>

          <div className="relative max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search client name, ref, route…"
              className="w-full rounded-xl border border-border bg-input/40 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
            />
          </div>
        </div>

        {viewMode === "list" ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
                <tr>
                  <th className="px-5 py-3.5">Ref #</th>
                  <th className="px-5 py-3.5">Client & Contact</th>
                  <th className="px-5 py-3.5">Move Route</th>
                  <th className="px-5 py-3.5">Date & Time</th>
                  <th className="px-5 py-3.5">Mode</th>
                  <th className="px-5 py-3.5">Status</th>
                  <th className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/40">
                {filteredSurveysList.map((s) => (
                  <tr key={s.id} className="hover:bg-card/30 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-gold">{s.surveyNumber}</td>
                    <td className="px-5 py-4">
                      <div className="font-semibold text-foreground text-sm">{s.clientName}</div>
                      <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                        <span className="flex items-center gap-1"><Mail className="h-3 w-3 text-gold/70" /> {s.clientEmail}</span>
                        <span className="flex items-center gap-1"><Phone className="h-3 w-3 text-gold/70" /> {s.clientPhone}</span>
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="text-xs font-semibold text-foreground">{s.location}</div>
                      <div className="text-[11px] text-muted-foreground mt-0.5">{s.moveType}</div>
                    </td>
                    <td className="px-5 py-4 text-xs">
                      <div className="font-semibold text-gold">{s.surveyDate}</div>
                      <div className="text-muted-foreground mt-0.5">{s.surveyTime}</div>
                    </td>
                    <td className="px-5 py-4">
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gold/10 text-gold border border-gold/30 capitalize">
                        {s.surveyType === "virtual" ? <Video className="h-3.5 w-3.5" /> : <MapPin className="h-3.5 w-3.5" />}
                        {s.surveyType} Survey
                      </span>
                    </td>
                    <td className="px-5 py-4">
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
                          s.status === "Completed"
                            ? "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"
                            : "bg-amber-500/15 text-amber-400 border border-amber-500/30"
                        }`}
                      >
                        {s.status === "Completed" ? <><CheckCircle2 className="h-3 w-3" /> Completed</> : <><Clock className="h-3 w-3" /> Pending</>}
                      </span>
                    </td>
                    <td className="px-5 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {/* Media Gallery Button — always visible */}
                        <button
                          onClick={() => setMediaGallerySurvey(s)}
                          className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-violet-500/15 border border-violet-500/30 text-xs font-semibold text-violet-400 hover:bg-violet-500/25 transition-all"
                          title="View Survey Media Gallery (Photos/Videos/Notes from App)"
                        >
                          <Camera className="h-3.5 w-3.5" />
                          <span className="hidden sm:inline">Media</span>
                        </button>

                        {s.surveyType === "virtual" && (
                          <button
                            onClick={() => handleLaunchVideoCall(s)}
                            className="px-2.5 py-1.5 rounded-lg bg-gold/15 border border-gold/30 text-xs font-semibold text-gold hover:bg-gold/25 transition-all"
                            title="Start Live Video Call Walkthrough"
                          >
                            <Video className="h-3.5 w-3.5" />
                          </button>
                        )}
                        <button
                          onClick={() => handleOpenUploadModal(s)}
                          className="px-2.5 py-1.5 rounded-lg border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-card transition-all"
                          title="Upload Survey Video Recording"
                        >
                          <Monitor className="h-3.5 w-3.5" />
                        </button>
                        <button
                          onClick={() => handleOpenReportModal(s)}
                          className="inline-flex items-center gap-1 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-3.5 py-1.5 text-xs font-bold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
                        >
                          {s.status === "Completed" ? "View / Edit Report" : "Conduct Report"}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {filteredSurveysList.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-5 py-12 text-center text-xs text-muted-foreground">
                      {loading ? "Loading assigned survey jobs..." : `No ${activeTab} survey assignments found.`}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-5 grid grid-cols-1 lg:grid-cols-12 gap-6 animate-in fade-in duration-200">
            {/* Calendar Grid Column (span 7) */}
            <div className="lg:col-span-7 space-y-4">
              {(() => {
                const monthStart = startOfMonth(calendarCursor);
                const days = eachDayOfInterval({
                  start: startOfWeek(monthStart, { weekStartsOn: 1 }),
                  end: endOfWeek(endOfMonth(calendarCursor), { weekStartsOn: 1 }),
                });

                return (
                  <div className="rounded-2xl border border-border/40 p-4 bg-background/25">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-sm font-semibold text-foreground">
                        {format(calendarCursor, "MMMM yyyy")}
                      </h3>
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => setCalendarCursor((c) => addMonths(c, -1))}
                          className="p-1.5 rounded-lg border border-border/60 hover:text-gold transition-colors text-muted-foreground"
                        >
                          <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => setCalendarCursor(new Date())}
                          className="px-2.5 py-1 rounded-lg border border-border/60 hover:text-gold text-xs font-semibold transition-colors text-muted-foreground"
                        >
                          Today
                        </button>
                        <button
                          onClick={() => setCalendarCursor((c) => addMonths(c, 1))}
                          className="p-1.5 rounded-lg border border-border/60 hover:text-gold transition-colors text-muted-foreground"
                        >
                          <ChevronRight className="h-4 w-4" />
                        </button>
                      </div>
                    </div>

                    {/* Weekdays */}
                    <div className="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
                      {["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map((d) => (
                        <div key={d} className="py-1">{d}</div>
                      ))}
                    </div>

                    {/* Days Grid */}
                    <div className="grid grid-cols-7 gap-1.5">
                      {days.map((day) => {
                        const daySurveys = surveysOn(day);
                        const isCurrentMonth = isSameMonth(day, calendarCursor);
                        const isSelected = isSameDay(day, selectedDate);
                        const isToday = isSameDay(day, new Date());

                        return (
                          <button
                            key={day.toISOString()}
                            onClick={() => setSelectedDate(day)}
                            className={`min-h-[56px] p-1.5 rounded-xl border flex flex-col justify-between text-left transition-all ${
                              isSelected
                                ? "bg-gold/15 border-gold text-foreground shadow-[0_0_12px_rgba(201,168,76,0.15)]"
                                : isToday
                                ? "border-gold/45 bg-gold/5 text-foreground"
                                : isCurrentMonth
                                ? "border-border/40 hover:border-gold/40 text-foreground"
                                : "border-border/20 text-muted-foreground/30 bg-muted/5"
                            }`}
                          >
                            <span className="text-[10px] font-mono font-bold">
                              {format(day, "d")}
                            </span>
                            {daySurveys.length > 0 && (
                              <div className="mt-1 flex flex-col gap-0.5 w-full">
                                <span className={`text-[8px] font-bold px-1 py-0.5 rounded text-center truncate ${
                                  daySurveys.every((s) => s.status === "Completed")
                                    ? "bg-success/10 text-success"
                                    : "bg-gold/10 text-gold"
                                }`}>
                                  {daySurveys.length} survey{daySurveys.length !== 1 ? "s" : ""}
                                </span>
                              </div>
                            )}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                );
              })()}
            </div>

            {/* Selected Day Duties List Column (span 5) */}
            <div className="lg:col-span-5 space-y-4">
              <div className="rounded-2xl border border-border/40 p-4 bg-background/25 flex flex-col h-full min-h-[300px]">
                <div className="border-b border-border/40 pb-3 mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Duties on Selected Date
                  </h4>
                  <p className="text-sm font-semibold text-gold mt-0.5">
                    {format(selectedDate, "eeee, d MMMM yyyy")}
                  </p>
                </div>

                {surveysOn(selectedDate).length === 0 ? (
                  <div className="flex-1 flex flex-col items-center justify-center text-center p-6 text-muted-foreground">
                    <Calendar className="h-8 w-8 mb-2 text-muted-foreground/30" />
                    <p className="text-xs">No survey duties scheduled for this day.</p>
                  </div>
                ) : (
                  <div className="space-y-3.5 overflow-y-auto max-h-[360px] pr-1">
                    {surveysOn(selectedDate).map((s) => (
                      <div
                        key={s.id}
                        className="p-3.5 rounded-xl border border-border/40 bg-card/20 space-y-3"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <span className="font-mono text-[10px] font-bold text-gold">
                              {s.surveyNumber}
                            </span>
                            <h5 className="font-semibold text-foreground text-sm mt-0.5">
                              {s.clientName}
                            </h5>
                          </div>
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold border capitalize shrink-0 ${
                            s.status === "Completed"
                              ? "bg-success/15 text-success border-success/30"
                              : "bg-gold/15 text-gold border-gold/30"
                          }`}>
                            {s.status === "Completed" ? "Completed" : "Pending"}
                          </span>
                        </div>

                        <div className="text-xs space-y-1.5 text-muted-foreground">
                          <p className="flex items-center gap-1.5">
                            <Clock className="h-3.5 w-3.5 text-gold/70 shrink-0" />
                            Slot: <strong className="text-foreground">{s.surveyTime}</strong> ({s.surveyType} Survey)
                          </p>
                          <p className="flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5 text-gold/70 shrink-0" />
                            Route: <strong className="text-foreground truncate" title={s.location}>{s.location}</strong>
                          </p>
                          <p className="flex items-center gap-1.5">
                            <Mail className="h-3.5 w-3.5 text-gold/70 shrink-0" />
                            Email: <span className="text-foreground truncate">{s.clientEmail}</span>
                          </p>
                          <p className="flex items-center gap-1.5">
                            <Phone className="h-3.5 w-3.5 text-gold/70 shrink-0" />
                            Phone: <span className="text-foreground font-mono">{s.clientPhone}</span>
                          </p>
                        </div>

                        {s.notes && (
                          <p className="text-[11px] text-muted-foreground bg-background/30 p-2 rounded-lg border border-border/30 italic">
                            "{s.notes}"
                          </p>
                        )}

                        <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-border/20">
                          {s.surveyType === "virtual" && (
                            <button
                              onClick={() => handleLaunchVideoCall(s)}
                              className="p-1.5 rounded-lg border border-border bg-card/60 text-xs font-semibold text-chart-3 hover:bg-chart-3/15 transition-all"
                              title="Start Live Video Call Walkthrough"
                            >
                              <Video className="h-3.5 w-3.5" />
                            </button>
                          )}
                          <button
                            onClick={() => handleOpenUploadModal(s)}
                            className="p-1.5 rounded-lg border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-card transition-all"
                            title="Upload Survey Video Recording"
                          >
                            <Monitor className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => handleOpenReportModal(s)}
                            className="flex-1 inline-flex items-center justify-center gap-1 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-3 py-1.5 text-xs font-bold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
                          >
                            {s.status === "Completed" ? "Edit Report" : "Conduct Survey"}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </GlassCard>

      {/* Survey Report Modal */}
      {selectedSurvey && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-3xl rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] max-h-[90vh] overflow-y-auto space-y-5">
            <button onClick={() => setSelectedSurvey(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gold/20 text-gold font-mono font-bold text-base border border-gold/30">SRV</div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="font-display text-2xl font-semibold text-foreground">{selectedSurvey.clientName}</h2>
                  <span className="rounded-full bg-gold/20 text-gold px-2.5 py-0.5 text-xs font-mono font-bold">{selectedSurvey.surveyNumber}</span>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Route: <strong className="text-foreground">{selectedSurvey.location}</strong> ({selectedSurvey.moveType})
                </p>
              </div>
            </div>

            {successMessage && (
              <div className="rounded-xl border border-emerald-500/40 bg-emerald-500/15 p-3 text-xs font-semibold text-emerald-400 flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4" /> {successMessage}
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs rounded-2xl bg-card/60 p-4 border border-border/40">
              <div>
                <span className="text-muted-foreground text-[10px] uppercase font-bold tracking-wider block">Client Contact</span>
                <span className="font-semibold text-foreground block mt-0.5">{selectedSurvey.clientEmail}</span>
                <span className="text-gold font-medium block">{selectedSurvey.clientPhone}</span>
              </div>
              <div>
                <span className="text-muted-foreground text-[10px] uppercase font-bold tracking-wider block">Survey Schedule</span>
                <span className="font-semibold text-gold block mt-0.5">{selectedSurvey.surveyDate}</span>
                <span className="text-muted-foreground block">{selectedSurvey.surveyTime}</span>
              </div>
              <div>
                <span className="text-muted-foreground text-[10px] uppercase font-bold tracking-wider block">Survey Mode</span>
                <span className="font-semibold capitalize text-foreground block mt-0.5">{selectedSurvey.surveyType} Survey</span>
              </div>
            </div>

            <form onSubmit={handleSubmitReport} className="space-y-4 pt-2">
              <div>
                <label className="mb-1.5 text-xs font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                  <FileText className="h-4 w-4" /> Surveyor Inspection Notes & Room Inventory
                </label>
                <textarea
                  rows={4}
                  required
                  value={reportNotes}
                  onChange={(e) => setReportNotes(e.target.value)}
                  placeholder="Note down property access details, room-by-room item inventory, special dismantling needs, packing materials required..."
                  className="w-full rounded-xl border border-border bg-input/40 p-3 text-xs text-foreground outline-none focus:border-gold/50"
                />
              </div>

              <div className="space-y-3">
                <label className="text-xs font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                  <ImageIcon className="h-4 w-4" /> Property Photos & Walkthrough Videos
                </label>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <label className="rounded-xl border border-dashed border-border/80 bg-muted/20 p-4 text-center cursor-pointer hover:bg-muted/40 transition-colors block">
                    <Upload className="mx-auto h-6 w-6 text-gold mb-1" />
                    <span className="text-xs text-muted-foreground block font-medium">Select Images / Video Files</span>
                    <input type="file" multiple accept="image/*,video/*" onChange={handleFileUpload} className="hidden" />
                  </label>
                  <div className="space-y-2">
                    <span className="text-xs text-muted-foreground block font-medium">Or Paste External Media URL</span>
                    <div className="flex gap-2">
                      <input
                        type="url"
                        value={newMediaInput}
                        onChange={(e) => setNewMediaInput(e.target.value)}
                        placeholder="https://..."
                        className="flex-1 rounded-xl border border-border bg-input/40 px-3 py-2 text-xs outline-none focus:border-gold/50"
                      />
                      <button type="button" onClick={handleAddMedia} className="rounded-xl bg-gold/20 border border-gold/30 px-3 py-2 text-xs font-bold text-gold hover:bg-gold/30">
                        Add URL
                      </button>
                    </div>
                  </div>
                </div>

                {mediaUrls.length > 0 && (
                  <div className="mt-3">
                    <span className="text-[11px] font-semibold text-muted-foreground block mb-2">Attached Media ({mediaUrls.length} files)</span>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 max-h-48 overflow-y-auto p-1 border border-border/40 rounded-xl">
                      {mediaUrls.map((url, i) => (
                        <div key={i} className="relative group rounded-lg overflow-hidden border border-gold/30 bg-black/60 h-24 flex items-center justify-center">
                          {url.startsWith("data:image") || url.match(/\.(jpeg|jpg|gif|png|webp)$/i) ? (
                            <img src={url} alt={`Media ${i}`} className="w-full h-full object-cover" />
                          ) : url.startsWith("data:video") || url.match(/\.(mp4|webm|mov)$/i) ? (
                            <video src={url} className="w-full h-full object-cover" />
                          ) : (
                            <div className="p-2 text-[10px] text-gold truncate text-center">{url}</div>
                          )}
                          <button type="button" onClick={() => handleRemoveMedia(i)} className="absolute top-1 right-1 rounded-full bg-black/80 text-white p-1 hover:bg-destructive">
                            <X className="h-3 w-3" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-border/60">
                <button type="button" onClick={() => setSelectedSurvey(null)} className="rounded-xl border border-border px-5 py-2 text-xs font-semibold text-muted-foreground hover:bg-card">
                  Cancel
                </button>
                <button type="submit" disabled={submitting} className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-6 py-2 text-xs font-bold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50">
                  {submitting ? "Filing Report…" : "Save & File Survey Report"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Survey Media Gallery */}
      {mediaGallerySurvey && (
        <SurveyMediaGallery
          survey={mediaGallerySurvey}
          onClose={() => setMediaGallerySurvey(null)}
        />
      )}

      {/* Live HD Video Call Modal */}
      <VideoCallModal
        open={videoCallOpen}
        onClose={() => setVideoCallOpen(false)}
        roomUrl={activeRoomUrl}
        roomCode={activeRoomCode}
        leadId={activeLeadId}
        leadName={activeLeadName}
      />

      {/* Video Survey Upload Modal */}
      <VideoUploadModal
        open={videoUploadOpen}
        onClose={() => setVideoUploadOpen(false)}
        leadId={activeLeadId}
        leadName={activeLeadName}
      />
    </div>
  );
}
