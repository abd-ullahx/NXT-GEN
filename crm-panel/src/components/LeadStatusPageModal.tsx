import { useState } from "react";
import {
  X,
  Sparkles,
  CheckCircle2,
  Clock,
  Send,
  AlertCircle,
  Calendar,
  MapPin,
  Truck,
  ArrowRight,
  ShieldCheck,
  Mail,
  Phone,
  FileText,
  User,
  ExternalLink,
  ChevronRight,
  AlertTriangle,
  StopCircle,
  SkipForward,
  Pencil,
} from "lucide-react";
import { Button } from "@/components/ui/button";

interface LeadStatusPageModalProps {
  lead: any;
  onClose: () => void;
  surveyReminders?: any[];
  remindersLoading?: boolean;
  onSkipReminder?: (reminderId: number) => void;
  onStopReminders?: () => void;
  onUpdateReminder?: (reminderId: number, scheduledAt: string) => void;
  quotationReminders?: any[];
  quoteRemindersLoading?: boolean;
  onSkipQuotationReminder?: (reminderId: number) => void;
  onStopQuotationReminders?: () => void;
  onUpdateQuotationReminder?: (reminderId: number, scheduledAt: string) => void;
  onOpenEmailTemplates?: () => void;
}

export function LeadStatusPageModal({
  lead,
  onClose,
  surveyReminders = [],
  remindersLoading = false,
  onSkipReminder,
  onStopReminders,
  onUpdateReminder,
  quotationReminders = [],
  quoteRemindersLoading = false,
  onSkipQuotationReminder,
  onStopQuotationReminders,
  onUpdateQuotationReminder,
  onOpenEmailTemplates,
}: LeadStatusPageModalProps) {
  const [activeTab, setActiveTab] = useState<"timeline" | "reminders" | "details">("timeline");
  const [editingReminderId, setEditingReminderId] = useState<number | null>(null);
  const [editingReminderType, setEditingReminderType] = useState<"survey" | "quotation" | null>(null);
  const [editTimeValue, setEditTimeValue] = useState("");

  // ─── Lifecycle Stage Definitions ─────────────────────────────────────────────
  // IMPORTANT: Each stage's `timestamp` must ONLY be truthy when that specific
  // stage has genuinely been reached. Use strict lead field gates — never reuse
  // an earlier timestamp for a later stage or the "Active Stage" marker will
  // jump ahead incorrectly.
  //
  // Gate logic (in order):
  //   S01  createdAt / welcomeEmailSentAt          — lead exists
  //   S02  welcomeEmailSentAt                       — welcome sent
  //   S03  surveyEmailSentAt                        — survey invite sent
  //   S04  surveyBookedAt || surveyRequestedAt      — client booked / admin scheduled
  //   S05  surveyorCompletedAt || surveyApprovedAt  — survey actually done & approved
  //   S06  quotationSentAt                          — quote sent to client
  //   S07  quotationApprovedAt                      — client accepted quote
  //   S08  status === "won" AND quotationApprovedAt — booking locked
  //   S09  invoiceIssuedAt                          — invoice raised
  //   S10  (future: pre-move checklist flag)
  //   S11  (future: move-day start flag)
  //   S12  stage === "Completed"
  //   S13  (future: aftercare flag)

  // Build a strictly-ordered gate: a later stage is only active if every
  // required gate field for that stage is non-null.
  const s01 = lead.createdAt || lead.welcomeEmailSentAt || null;
  const s02 = lead.welcomeEmailSentAt || null;
  const s03 = lead.surveyEmailSentAt || null;
  const s04 = (lead.surveyBookedAt || lead.surveyRequestedAt) || null;
  const s05 = (lead.surveyorCompletedAt || lead.surveyApprovedAt) || null;
  const s06 = lead.quotationSentAt || null;
  const s07 = lead.quotationApprovedAt || null;
  const s08 = lead.quotationApprovedAt || lead.depositPaidAt || (lead.status === "won" && lead.quotationApprovedAt) ? (lead.quotationApprovedAt || lead.depositPaidAt) : null;
  const s09 = lead.invoiceIssuedAt || null;
  const s10 = (s08 && lead.moveDate) ? (lead.depositPaidAt || lead.quotationApprovedAt) : null; // pre-move preparation active after deposit
  const s11 = null; // future: move-day live
  const s12 = lead.stage === "Completed" ? (lead.moveDate || lead.updatedAt) : null;
  const s13 = null; // future: aftercare

  const allStages = [
    {
      id: "enquiry",
      title: "01 | New Enquiry & Welcome",
      badge: "Qualification",
      desc: "Lead received & welcome email dispatched. Initial client qualification.",
      timestamp: s01,
      category: "Category 01 — Qualification",
    },
    {
      id: "indicative_quote",
      title: "02 | Indicative Estimate",
      badge: "Estimate",
      desc: "Indicative quote calculated & shared with client before survey.",
      timestamp: s02,
      category: "Category 01 — Qualification",
    },
    {
      id: "survey_suggested",
      title: "03 | Survey Suggested / Required",
      badge: "Action Required",
      desc: "Survey invitation sent to verify property access, stairs, lifts & inventory.",
      timestamp: s03,
      category: "Category 01 — Qualification",
    },
    {
      id: "survey_scheduled",
      title: "04 | Survey Scheduled",
      badge: "Survey Booked",
      desc: "Video or physical survey confirmed with surveyor assigned.",
      timestamp: s04,
      category: "Category 02 — Survey Attendance",
    },
    {
      id: "survey_completed",
      title: "05 | Survey Completed",
      badge: "Survey Done",
      desc: "On-site/video inspection finished. Scope & inventory passed to estimator.",
      timestamp: s05,
      category: "Category 02 — Survey Attendance",
    },
    {
      id: "quote_issued",
      title: "06 | Quotation Issued",
      badge: "Proposal Sent",
      desc: "Bronze / Final Signature Proposal issued with secure acceptance link.",
      timestamp: s06,
      category: "Category 03 — Quotation & Conversion",
    },
    {
      id: "quote_accepted",
      title: "07 | Quotation Accepted",
      badge: "Milestone",
      desc: "Client accepted terms & quotation. Moved to deposit stage.",
      timestamp: s07,
      category: "Category 03 — Quotation & Conversion",
    },
    {
      id: "booking_confirmed",
      title: "08 | Booking Confirmed",
      badge: "In Diary",
      desc: "Deposit received & move date locked in master diary.",
      timestamp: s08,
      category: "Category 04 — Booking & Pre-Dispatch",
    },
    {
      id: "invoice_generated",
      title: "09 | Invoice Generated",
      badge: "Invoiced",
      desc: "Final draft invoice generated and ready for review/dispatch.",
      timestamp: s09,
      category: "Category 04 — Booking & Pre-Dispatch",
    },
    {
      id: "pre_move",
      title: "10 | Pre-Move Preparation",
      badge: "Countdown",
      desc: "30d, 21d, 14d, 7d, 3d, 1d pre-move operational preparation checklists.",
      timestamp: s10,
      category: "Category 05 — Pre-Move Preparation",
    },
    {
      id: "move_day",
      title: "11 | Move Day Live Execution",
      badge: "Live Dispatch",
      desc: "Crew en route, loading, in transit & destination unloading updates.",
      timestamp: s11,
      category: "Category 06 — Move Day Execution",
    },
    {
      id: "move_completed",
      title: "12 | Move Completed",
      badge: "Finished",
      desc: "Relocation complete. Final placement & keys handover complete.",
      timestamp: s12,
      category: "Category 07 — Aftercare",
    },
    {
      id: "aftercare",
      title: "13 | Aftercare, Reviews & Referral",
      badge: "Aftercare",
      desc: "Settling in 48h check, review invitation & private referral link active.",
      timestamp: s13,
      category: "Category 07 — Aftercare & Referral",
    },
  ];

  // Determine current active stage index
  const stageNames = allStages.map((s) => s.id);
  const currentStageIndex = Math.max(
    0,
    allStages.map(s => !!s.timestamp).lastIndexOf(true)
  );

  const pendingReminders = surveyReminders.filter((r) => r.status === "pending");

  return (
    <div className="fixed inset-0 z-50 bg-[#07080A] h-screen w-screen flex flex-col overflow-hidden animate-in fade-in duration-200">
      {/* Top Navigation Header */}
      <div className="shrink-0 bg-[#0F1017] border-b border-gold/20 px-6 py-4 flex items-center justify-between shadow-2xl z-30">
        <div className="flex items-center gap-4">
          <div className="p-3 rounded-2xl border border-gold/30 bg-gold/10 text-gold shadow-[var(--shadow-gold)]">
            <Sparkles className="h-6 w-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-display text-2xl font-bold text-foreground text-base">
                {lead.name || "Lead Status Tracker"}
              </h1>
              <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gold/20 text-gold border border-gold/30">
                {lead.stage || "Welcome Email"}
              </span>
              <span className="text-xs font-mono text-muted-foreground">({lead.id})</span>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5 flex items-center gap-3">
              <span>Email: <strong className="text-foreground">{lead.email}</strong></span>
              <span>Phone: <strong className="text-foreground">{lead.phone || "N/A"}</strong></span>
              <span>Source: <strong className="text-gold">{lead.source}</strong></span>
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          {onOpenEmailTemplates && (
            <Button
              onClick={onOpenEmailTemplates}
              className="bg-gradient-to-r from-gold-bright to-gold-dim text-primary-foreground font-semibold shadow-[var(--shadow-gold)]"
              size="sm"
            >
              <Mail className="h-4 w-4 mr-1.5" /> Send Luxury Template Email
            </Button>
          )}
          <button
            onClick={onClose}
            className="p-2.5 rounded-xl border border-border/60 bg-card/60 text-muted-foreground hover:text-foreground hover:border-gold/40 transition-all"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
      </div>

      {/* Main Container - Left Column Sticky, Right Stage Section Independently Scrollable */}
      <div className="flex-1 max-w-7xl w-full mx-auto p-6 lg:p-8 grid grid-cols-12 gap-8 overflow-hidden h-[calc(100vh-80px)]">
        
        {/* Left Column: Fixed / Sticky Move Summary & Survey Reminders */}
        <div className="col-span-12 lg:col-span-4 space-y-6 max-h-[calc(100vh-120px)] overflow-y-auto pr-2 custom-scrollbar">
          <div className="glass-card rounded-3xl p-6 border-gold/25 space-y-5 bg-[#0F1017]">
            <div className="flex items-center justify-between border-b border-border/60 pb-4">
              <span className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-1.5">
                <FileText className="h-4 w-4" /> Move Summary
              </span>
              <span className="text-xs text-muted-foreground font-mono">Ref: {lead.id}</span>
            </div>

            <div className="grid grid-cols-2 gap-4 text-xs">
              <div className="bg-background/40 p-3 rounded-xl border border-border/40">
                <div className="text-muted-foreground uppercase text-xs tracking-wider">Move Date</div>
                <div className="font-bold text-foreground text-base text-sm mt-0.5">
                  {lead.moveDate ? new Date(lead.moveDate).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }) : "Pending"}
                </div>
              </div>

              <div className="bg-background/40 p-3 rounded-xl border border-border/40">
                <div className="text-muted-foreground uppercase text-xs tracking-wider">Estimated Value</div>
                <div className="font-semibold text-gold text-sm mt-0.5">
                  £{Number(lead.estValue || 0).toLocaleString()}
                </div>
              </div>

              <div className="bg-background/40 p-3 rounded-xl border border-border/40 col-span-2">
                <div className="text-muted-foreground uppercase text-xs tracking-wider">Collection & Destination</div>
                <div className="font-medium text-foreground text-xs mt-1 flex items-center gap-1">
                  <MapPin className="h-3.5 w-3.5 text-gold shrink-0" />
                  <span className="truncate">{lead.from || "Collection TBC"}</span>
                  <ArrowRight className="h-3 w-3 text-muted-foreground shrink-0" />
                  <span className="truncate">{lead.to || "Destination TBC"}</span>
                </div>
              </div>

              <div className="bg-background/40 p-3 rounded-xl border border-border/40">
                <div className="text-muted-foreground uppercase text-xs tracking-wider">Move Type</div>
                <div className="font-medium text-foreground mt-0.5">{lead.moveType || "Standard Removal"}</div>
              </div>

              <div className="bg-background/40 p-3 rounded-xl border border-border/40">
                <div className="text-muted-foreground uppercase text-xs tracking-wider">Priority</div>
                <div className="font-semibold text-amber-400 mt-0.5">{lead.priority || "Warm"}</div>
              </div>
            </div>

            {/* Portal Link */}
            <div className="pt-2">
              <a
                href={`http://127.0.0.1:8000/portal/${lead.id}`}
                target="_blank"
                rel="noreferrer"
                className="w-full flex items-center justify-center gap-2 p-3 rounded-xl border border-gold/30 bg-gold/10 text-gold text-xs font-semibold hover:bg-gold/20 transition-all"
              >
                <ExternalLink className="h-4 w-4" /> Open Client Portal Link
              </a>
            </div>

            {/* Video Call & Survey Notes Box */}
            {(lead.surveyorReportNotes || lead.surveyNotes) && (
              <div className="rounded-2xl border border-gold/30 bg-gold/5 p-4 space-y-2">
                <div className="text-xs font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                  <FileText className="h-4 w-4" /> 📹 Video Call Notes
                </div>
                <p className="text-xs text-foreground leading-relaxed whitespace-pre-wrap font-sans bg-background/50 p-3 rounded-xl border border-border/40 max-h-48 overflow-y-auto">
                  {lead.surveyorReportNotes || lead.surveyNotes}
                </p>
              </div>
            )}
          </div>

          {/* Survey Reminders Card - Only show if survey is not completed */}
          {(!lead.surveyApprovedAt && !lead.surveyorCompletedAt) && (
          <div className="glass-card rounded-3xl p-6 border-gold/25 bg-[#0F1017] space-y-4">
            <div className="flex items-center justify-between border-b border-border/60 pb-3">
              <span className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-1.5">
                <Mail className="h-4 w-4" /> Survey Reminders Schedule
              </span>
              {pendingReminders.length > 0 && onStopReminders && (
                <button
                  onClick={onStopReminders}
                  className="text-sm font-semibold text-destructive hover:underline flex items-center gap-1"
                >
                  <StopCircle className="h-3.5 w-3.5" /> Stop All ({pendingReminders.length})
                </button>
              )}
            </div>

            {remindersLoading ? (
              <div className="text-xs text-muted-foreground py-6 text-center">Loading reminder slots…</div>
            ) : surveyReminders.length === 0 ? (
              <div className="p-4 rounded-xl border border-dashed border-border/50 text-center text-xs text-muted-foreground">
                No active survey reminders scheduled.
              </div>
            ) : (
              <div className="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
                {surveyReminders.map((r: any) => (
                  <div
                    key={r.id}
                    className="p-3 rounded-xl border border-border/50 bg-background/30 flex items-center justify-between text-sm"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="font-bold text-foreground text-base">{r.label}</div>
                      {editingReminderId === r.id && editingReminderType === "survey" ? (
                        <div className="flex items-center gap-1.5 mt-1">
                          <input
                            type="datetime-local"
                            value={editTimeValue}
                            onChange={(e) => setEditTimeValue(e.target.value)}
                            className="bg-input border border-border rounded px-1.5 py-0.5 text-xs text-foreground outline-none w-48 text-white"
                          />
                          <button
                            onClick={() => {
                              if (onUpdateReminder && editTimeValue) {
                                onUpdateReminder(r.id, editTimeValue);
                              }
                              setEditingReminderId(null);
                              setEditingReminderType(null);
                            }}
                            className="text-success hover:underline text-xs font-bold"
                          >
                            Save
                          </button>
                          <button
                            onClick={() => {
                              setEditingReminderId(null);
                              setEditingReminderType(null);
                            }}
                            className="text-muted-foreground hover:underline text-xs"
                          >
                            Cancel
                          </button>
                        </div>
                      ) : (
                        <div className="text-xs text-muted-foreground font-mono mt-0.5 flex items-center gap-1">
                          {r.scheduledAt ? new Date(r.scheduledAt).toLocaleString("en-GB", { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : "Time pending"}
                          {r.status === "pending" && onUpdateReminder && (
                            <button
                              onClick={() => {
                                setEditingReminderId(r.id);
                                setEditingReminderType("survey");
                                setEditTimeValue(r.scheduledAt ? new Date(r.scheduledAt).toISOString().slice(0, 16) : "");
                              }}
                              className="text-gold hover:underline text-xs ml-1.5 flex items-center gap-0.5"
                            >
                              <Pencil className="h-3.5 w-3.5" /> Edit
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-2 shrink-0 ml-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-semibold capitalize border ${
                        r.status === "sent" ? "bg-success/15 text-success border-success/30" : "bg-gold/15 text-gold border-gold/30"
                      }`}>
                        {r.status}
                      </span>
                      {r.status === "pending" && onSkipReminder && (
                        <button
                          onClick={() => onSkipReminder(r.id)}
                          className="p-1 text-muted-foreground hover:text-amber-400"
                          title="Skip reminder"
                        >
                          <SkipForward className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
          )}

          {/* Quotation Reminders Card - Only show if current quotation status is exactly 'sent' */}
          {(lead.quotationStatus === "sent") && (
          <div className="glass-card rounded-3xl p-6 border-gold/25 bg-[#0F1017] space-y-4">
            <div className="flex items-center justify-between border-b border-border/60 pb-3">
              <span className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-1.5">
                <FileText className="h-4 w-4" /> Quotation Reminders Schedule
              </span>
              {quotationReminders.filter((r: any) => r.status === "pending").length > 0 && onStopQuotationReminders && (
                <button
                  onClick={onStopQuotationReminders}
                  className="text-sm font-semibold text-destructive hover:underline flex items-center gap-1"
                >
                  <StopCircle className="h-3.5 w-3.5" /> Stop All ({quotationReminders.filter((r: any) => r.status === "pending").length})
                </button>
              )}
            </div>

            {quoteRemindersLoading ? (
              <div className="text-xs text-muted-foreground py-6 text-center">Loading reminder slots…</div>
            ) : quotationReminders.length === 0 ? (
              <div className="p-4 rounded-xl border border-dashed border-border/50 text-center text-xs text-muted-foreground">
                No quotation reminders scheduled. Send the quotation to schedule reminders automatically.
              </div>
            ) : (
              <div className="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
                {quotationReminders.map((r: any) => (
                  <div
                    key={r.id}
                    className="p-3 rounded-xl border border-border/50 bg-background/30 flex items-center justify-between text-sm"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="font-bold text-foreground text-base">{r.label}</div>
                      {editingReminderId === r.id && editingReminderType === "quotation" ? (
                        <div className="flex items-center gap-1.5 mt-1">
                          <input
                            type="datetime-local"
                            value={editTimeValue}
                            onChange={(e) => setEditTimeValue(e.target.value)}
                            className="bg-input border border-border rounded px-1.5 py-0.5 text-xs text-foreground outline-none w-48 text-white"
                          />
                          <button
                            onClick={() => {
                              if (onUpdateQuotationReminder && editTimeValue) {
                                onUpdateQuotationReminder(r.id, editTimeValue);
                              }
                              setEditingReminderId(null);
                              setEditingReminderType(null);
                            }}
                            className="text-success hover:underline text-xs font-bold"
                          >
                            Save
                          </button>
                          <button
                            onClick={() => {
                              setEditingReminderId(null);
                              setEditingReminderType(null);
                            }}
                            className="text-muted-foreground hover:underline text-xs"
                          >
                            Cancel
                          </button>
                        </div>
                      ) : (
                        <div className="text-xs text-muted-foreground font-mono mt-0.5 flex items-center gap-1">
                          {r.scheduledAt ? new Date(r.scheduledAt).toLocaleString("en-GB", { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : "Time pending"}
                          {r.status === "pending" && onUpdateQuotationReminder && (
                            <button
                              onClick={() => {
                                setEditingReminderId(r.id);
                                setEditingReminderType("quotation");
                                setEditTimeValue(r.scheduledAt ? new Date(r.scheduledAt).toISOString().slice(0, 16) : "");
                              }}
                              className="text-gold hover:underline text-xs ml-1.5 flex items-center gap-0.5"
                            >
                              <Pencil className="h-3.5 w-3.5" /> Edit
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-2 shrink-0 ml-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-semibold border capitalize ${
                        r.status === "sent" ? "bg-success/15 text-success border-success/30" : "bg-gold/15 text-gold border-gold/30"
                      }`}>
                        {r.status}
                      </span>
                      {r.status === "pending" && onSkipQuotationReminder && (
                        <button
                          onClick={() => onSkipQuotationReminder(r.id)}
                          className="p-1 text-muted-foreground hover:text-amber-400"
                          title="Skip reminder"
                        >
                          <SkipForward className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
          )}
        </div>

        {/* Right Column: Full Lifecycle 13-Stage Timeline (Independently Scrollable) */}
        <div className="col-span-12 lg:col-span-8 flex flex-col max-h-[calc(100vh-120px)] h-full overflow-hidden">
          <div className="glass-card rounded-3xl p-6 lg:p-8 border-gold/25 bg-[#0F1017] flex-1 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between border-b border-border/60 pb-5 mb-6 shrink-0">
              <div>
                <h2 className="font-display text-2xl font-semibold text-foreground">
                  Complete Relocation Lifecycle (13 Stages)
                </h2>
                <p className="text-xs text-muted-foreground mt-1">
                  Automated tracking of enquiry, survey, quotation, pre-move preparation & live execution.
                </p>
              </div>
              <div className="rounded-full bg-gold/15 px-3 py-1 text-xs font-semibold text-gold border border-gold/30">
                {currentStageIndex + 1} of 13 Stages Active
              </div>
            </div>

            {/* Stepper Timeline Grid — Scrollable */}
            <div className="space-y-4 flex-1 overflow-y-auto pr-3 custom-scrollbar">
              {allStages.map((stg, idx) => {
                const isPassed = idx <= currentStageIndex;
                const isCurrent = idx === currentStageIndex;
                const isDone = !!stg.timestamp;

                return (
                  <div
                    key={stg.id}
                    className={`relative p-5 rounded-2xl border transition-all ${
                      isCurrent
                        ? "bg-gold/15 border-gold shadow-[var(--shadow-gold)] scale-[1.01]"
                        : isDone
                        ? "bg-card/40 border-gold/30"
                        : "bg-background/20 border-border/40 opacity-70"
                    }`}
                  >
                    <div className="flex items-start gap-4">
                      {/* Step Circle */}
                      <div
                        className={`flex h-10 w-10 items-center justify-center rounded-2xl text-xs font-bold shrink-0 transition-all ${
                          isDone
                            ? "bg-gold text-black font-black"
                            : isCurrent
                            ? "bg-gold/30 text-gold border-2 border-gold"
                            : "bg-muted/40 text-muted-foreground"
                        }`}
                      >
                        {isDone ? <CheckCircle2 className="h-5 w-5" /> : idx + 1}
                      </div>

                      {/* Content details */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <h3 className="text-sm font-bold text-foreground text-base">{stg.title}</h3>
                            <span className="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gold/10 text-gold border border-gold/20">
                              {stg.badge}
                            </span>
                          </div>
                          {isCurrent && (
                            <span className="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-gold text-black">
                              Active Stage
                            </span>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
                          {stg.desc}
                        </p>

                        <div className="flex items-center justify-between mt-3 pt-3 border-t border-border/40 text-sm">
                          <span className="text-muted-foreground font-mono">
                            Category: <strong className="text-gold/90">{stg.category}</strong>
                          </span>
                          {stg.timestamp ? (
                            <span className="text-emerald-400 font-mono flex items-center gap-1 font-medium">
                              <CheckCircle2 className="h-3.5 w-3.5" /> Completed: {new Date(stg.timestamp).toLocaleString("en-GB", { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true })}
                            </span>
                          ) : (
                            <span className="text-muted-foreground italic font-mono flex items-center gap-1">
                              <Clock className="h-3.5 w-3.5 text-muted-foreground" /> Pending automated trigger
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
