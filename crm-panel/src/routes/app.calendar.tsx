import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
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
import { ChevronLeft, ChevronRight, Plus, CalendarDays, Truck, Users, Wrench, Phone, Bell, X } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { eventTypeMeta, type JobEvent } from "@/lib/mock-data";
import { createCalendarEvent, updateCalendarEvent } from "@/lib/api";
import { useCalendarEventsQuery } from "@/lib/queries";
import { useQueryClient } from "@tanstack/react-query";

export const Route = createFileRoute("/app/calendar")({
  head: () => ({ meta: [{ title: "Calendar — Next Gen Relocation CRM" }] }),
  component: CalendarPage,
});

const typeIcon: Record<JobEvent["type"], typeof Truck> = { Job: Truck, Survey: Users, Call: Phone, Maintenance: Wrench, Reminder: Bell };

function CalendarPage() {
  const queryClient = useQueryClient();
  const { data: jobEvents = [] } = useCalendarEventsQuery();
  const [cursor, setCursor] = useState<Date>(new Date());
  const [selected, setSelected] = useState<Date>(new Date());
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [rescheduleEvent, setRescheduleEvent] = useState<JobEvent | null>(null);
  const [rescheduleData, setRescheduleData] = useState({ date: "", time: "" });
  const [formData, setFormData] = useState({
    title: "",
    type: "Job" as JobEvent["type"],
    date: format(new Date(), "yyyy-MM-dd"),
    time: "09:30 AM",
    clientName: "",
    clientEmail: "",
    clientPhone: "",
    moveType: "House Move",
    fromLocation: "",
    toLocation: "",
    driver: "Tomasz K.",
    vehicle: "3.5t Luton Van",
    location: "",
    estValue: "",
    notes: "",
  });

  const handleCreateEvent = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createCalendarEvent({
        ...formData,
        location: formData.location || (formData.fromLocation ? `${formData.fromLocation} → ${formData.toLocation}` : ""),
      });
      setIsModalOpen(false);
      setFormData({
        title: "",
        type: "Job",
        date: format(selected, "yyyy-MM-dd"),
        time: "09:30 AM",
        clientName: "",
        clientEmail: "",
        clientPhone: "",
        moveType: "House Move",
        fromLocation: "",
        toLocation: "",
        driver: "Tomasz K.",
        vehicle: "3.5t Luton Van",
        location: "",
        estValue: "",
        notes: "",
      });
      queryClient.invalidateQueries({ queryKey: ["calendar-events"] });
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    } catch (err) {
      console.error("Error creating job event:", err);
    }
  };

  const monthStart = startOfMonth(cursor);
  const days = eachDayOfInterval({
    start: startOfWeek(monthStart, { weekStartsOn: 1 }),
    end: endOfWeek(endOfMonth(cursor), { weekStartsOn: 1 }),
  });

  const eventsOn = (d: Date) => jobEvents.filter((e) => isSameDay(new Date(e.date), d));
  const selectedEvents = eventsOn(selected);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <CalendarDays className="h-3.5 w-3.5" /> Scheduling
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Operations Calendar</h1>
          <p className="text-sm text-muted-foreground">Schedule & reschedule jobs, drivers, fleet, surveys and calls in MySQL.</p>
        </div>
        <button
          onClick={() => {
            setFormData({ ...formData, date: format(selected, "yyyy-MM-dd") });
            setIsModalOpen(true);
          }}
          className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]"
        >
          <Plus className="h-4 w-4" /> Schedule Job
        </button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <GlassCard className="p-5 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="font-display text-xl font-semibold">{format(cursor, "MMMM yyyy")}</h2>
            <div className="flex items-center gap-1">
              <button onClick={() => setCursor(addMonths(cursor, -1))} className="rounded-lg border border-border bg-card/40 p-1.5 hover:border-gold/40">
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button onClick={() => setCursor(addMonths(cursor, 1))} className="rounded-lg border border-border bg-card/40 p-1.5 hover:border-gold/40">
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="grid grid-cols-7 gap-1 text-center text-[11px] uppercase tracking-wider text-muted-foreground">
            {["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map((d) => (
              <div key={d} className="py-2">{d}</div>
            ))}
          </div>
          <div className="grid grid-cols-7 gap-1">
            {days.map((d) => {
              const evs = eventsOn(d);
              const inMonth = isSameMonth(d, cursor);
              const isSel = isSameDay(d, selected);
              return (
                <button
                  key={d.toISOString()}
                  onClick={() => setSelected(d)}
                  className={`flex min-h-[72px] flex-col rounded-xl border p-1.5 text-left transition-all ${
                    isSel ? "border-gold/50 bg-gold/10" : "border-border/40 hover:border-gold/30"
                  } ${inMonth ? "" : "opacity-35"}`}
                >
                  <span className={`text-xs ${isSel ? "font-semibold text-gold" : "text-muted-foreground"}`}>{format(d, "d")}</span>
                  <div className="mt-1 space-y-0.5">
                    {evs.slice(0, 2).map((e) => {
                      const meta = eventTypeMeta[e.type as keyof typeof eventTypeMeta] || eventTypeMeta["Job"];
                      return (
                        <div key={e.id} className={`flex items-center gap-1 rounded px-1 py-0.5 text-[9px] ${meta.chip}`}>
                          <span className={`h-1 w-1 rounded-full ${meta.dot}`} />
                          <span className="truncate">{e.time}</span>
                        </div>
                      );
                    })}
                    {evs.length > 2 && <div className="text-[9px] text-muted-foreground">+{evs.length - 2} more</div>}
                  </div>
                </button>
              );
            })}
          </div>
        </GlassCard>

        {/* Day detail */}
        <GlassCard className="p-5">
          <h2 className="font-display text-xl font-semibold">{format(selected, "EEEE, d MMM")}</h2>
          <p className="text-xs text-muted-foreground">{selectedEvents.length} scheduled item(s)</p>
          <div className="mt-4 space-y-3">
            {selectedEvents.length === 0 && (
              <div className="rounded-xl border border-dashed border-border/60 p-6 text-center text-sm text-muted-foreground">
                Nothing scheduled. Click “Schedule Job” to add.
              </div>
            )}
            {selectedEvents.map((e: JobEvent) => {
              const Icon = typeIcon[e.type as keyof typeof typeIcon] || Truck;
              const meta = eventTypeMeta[e.type as keyof typeof eventTypeMeta] || eventTypeMeta["Job"];
              return (
                <div key={e.id} className="rounded-xl border border-border/60 bg-card/40 p-4">
                  <div className="flex items-center justify-between">
                    <span className={`inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-[10px] ${meta.chip}`}>
                      <Icon className="h-3 w-3" /> {e.type}
                    </span>
                    <span className="text-sm font-semibold text-gold">{e.time}</span>
                  </div>
                  <div className="mt-2 text-sm font-medium">{e.title}</div>
                  <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                    <div>📍 {e.location}</div>
                    <div>👤 {e.driver}</div>
                    {e.vehicle !== "—" && <div>🚚 {e.vehicle}</div>}
                  </div>
                  <div className="mt-3 flex gap-2">
                    <button
                      onClick={() => {
                        setRescheduleEvent(e);
                        setRescheduleData({ date: e.date, time: e.time });
                      }}
                      className="flex-1 rounded-lg border border-gold/40 py-1.5 text-xs font-semibold text-gold hover:bg-gold/10 transition-all"
                    >
                      Reschedule Time / Date
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </GlassCard>
      </div>

      {/* Reschedule Event Modal */}
      {rescheduleEvent && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
            <button onClick={() => setRescheduleEvent(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>
            <h2 className="font-display text-xl font-semibold mb-1">Reschedule Event / Reminder</h2>
            <p className="text-xs text-muted-foreground mb-4">Change scheduled execution date & time in MySQL.</p>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                try {
                  await updateCalendarEvent(rescheduleEvent.id, rescheduleData);
                  setRescheduleEvent(null);
                  queryClient.invalidateQueries({ queryKey: ["calendar-events"] });
                } catch (err) {
                  console.error("Reschedule failed:", err);
                }
              }}
              className="space-y-4"
            >
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Event Title</label>
                <div className="rounded-xl border border-border bg-card/60 p-2.5 text-xs font-medium text-foreground">
                  {rescheduleEvent.title}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">New Date</label>
                  <input
                    type="date"
                    required
                    value={rescheduleData.date}
                    onChange={(e) => setRescheduleData({ ...rescheduleData, date: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">New Time</label>
                  <input
                    required
                    value={rescheduleData.time}
                    onChange={(e) => setRescheduleData({ ...rescheduleData, time: e.target.value })}
                    placeholder="11:30 AM"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setRescheduleEvent(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
                >
                  Save Rescheduled Time
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal dialog */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-3 sm:p-4 overflow-y-auto">
          <div className="glass-card w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl relative animate-in fade-in zoom-in duration-150 border border-gold/30 bg-[#0A0B0E] shadow-2xl overflow-hidden">
            {/* Header */}
            <div className="p-4 sm:p-6 pb-3 border-b border-border/60 flex items-center justify-between shrink-0 bg-[#0A0B0E]">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold font-mono font-bold text-sm border border-gold/30">
                  <CalendarDays className="h-5 w-5" />
                </div>
                <div>
                  <h2 className="font-display text-xl sm:text-2xl font-semibold text-foreground">Schedule Event & Job</h2>
                  <p className="text-[11px] sm:text-xs text-muted-foreground">Add new job or survey event with full client and move details.</p>
                </div>
              </div>
              <button onClick={() => setIsModalOpen(false)} className="text-muted-foreground hover:text-foreground rounded-lg p-1.5 hover:bg-card">
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Scrollable Form Body */}
            <form onSubmit={handleCreateEvent} className="flex flex-col flex-1 overflow-hidden">
              <div className="p-4 sm:p-6 overflow-y-auto space-y-4 text-xs custom-scrollbar">
                {/* Section 1: Client Information */}
                <div className="rounded-xl border border-border/60 bg-card/40 p-3.5 sm:p-4 space-y-3">
                  <div className="text-[11px] font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5" /> Client & Contact Details
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="mb-1 block text-muted-foreground">Client Name</label>
                      <input
                        type="text"
                        placeholder="e.g. Alexander Wright"
                        value={formData.clientName}
                        onChange={(e) => setFormData({ ...formData, clientName: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Client Email</label>
                      <input
                        type="email"
                        placeholder="alexander@example.com"
                        value={formData.clientEmail}
                        onChange={(e) => setFormData({ ...formData, clientEmail: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Client Phone</label>
                      <input
                        type="tel"
                        placeholder="+44 7700 900123"
                        value={formData.clientPhone}
                        onChange={(e) => setFormData({ ...formData, clientPhone: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                  </div>
                </div>

                {/* Section 2: Event & Schedule Info */}
                <div className="rounded-xl border border-border/60 bg-card/40 p-3.5 sm:p-4 space-y-3">
                  <div className="text-[11px] font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                    <CalendarDays className="h-3.5 w-3.5" /> Event & Timing
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="mb-1 block text-muted-foreground">Event Title (Optional)</label>
                      <input
                        value={formData.title}
                        onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                        placeholder="e.g. Whitmore — 4-Bed Move"
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Move / Service Type</label>
                      <select
                        value={formData.moveType}
                        onChange={(e) => setFormData({ ...formData, moveType: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      >
                        <option value="House Move" className="bg-card">House Move</option>
                        <option value="Office Relocation" className="bg-card">Office Relocation</option>
                        <option value="Luxury Removals" className="bg-card">Luxury Removals</option>
                        <option value="Packing & Transit" className="bg-card">Packing & Transit</option>
                        <option value="Storage Transport" className="bg-card">Storage Transport</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="mb-1 block text-muted-foreground">Event Type</label>
                      <select
                        value={formData.type}
                        onChange={(e) => setFormData({ ...formData, type: e.target.value as JobEvent["type"] })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      >
                        <option value="Job" className="bg-card">Job</option>
                        <option value="Survey" className="bg-card">Survey</option>
                        <option value="Call" className="bg-card">Call</option>
                        <option value="Maintenance" className="bg-card">Maintenance</option>
                        <option value="Reminder" className="bg-card">Reminder</option>
                      </select>
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Scheduled Date</label>
                      <input
                        type="date"
                        required
                        value={formData.date}
                        onChange={(e) => setFormData({ ...formData, date: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Exact Time</label>
                      <input
                        type="text"
                        placeholder="e.g. 09:30 AM"
                        value={formData.time}
                        onChange={(e) => setFormData({ ...formData, time: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50 font-mono"
                      />
                    </div>
                  </div>
                </div>

                {/* Section 3: Locations & Fleet */}
                <div className="rounded-xl border border-border/60 bg-card/40 p-3.5 sm:p-4 space-y-3">
                  <div className="text-[11px] font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                    <Truck className="h-3.5 w-3.5" /> Logistics & Fleet Allocation
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="mb-1 block text-muted-foreground">Collection Location (From)</label>
                      <input
                        placeholder="e.g. Mayfair, London"
                        value={formData.fromLocation}
                        onChange={(e) => setFormData({ ...formData, fromLocation: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Delivery Location (To)</label>
                      <input
                        placeholder="e.g. Kensington, London"
                        value={formData.toLocation}
                        onChange={(e) => setFormData({ ...formData, toLocation: e.target.value })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="mb-1 block text-muted-foreground">Assigned Driver / Surveyor</label>
                      <input
                        value={formData.driver}
                        onChange={(e) => setFormData({ ...formData, driver: e.target.value })}
                        placeholder="e.g. Tomasz K."
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-muted-foreground">Allocated Vehicle</label>
                      <input
                        value={formData.vehicle}
                        onChange={(e) => setFormData({ ...formData, vehicle: e.target.value })}
                        placeholder="e.g. 3.5t Luton Van · NG21"
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                      />
                    </div>
                  </div>
                </div>

                {/* Section 4: Pricing & Notes */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="mb-1 block text-muted-foreground">Estimated Value (£)</label>
                    <input
                      type="number"
                      step="0.01"
                      placeholder="1500.00"
                      value={formData.estValue}
                      onChange={(e) => setFormData({ ...formData, estValue: e.target.value })}
                      className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50 font-mono"
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <label className="mb-1 block text-muted-foreground">Special Instructions / Inventory Scope</label>
                    <input
                      placeholder="e.g. Piano handling, full packing required."
                      value={formData.notes}
                      onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                      className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-foreground outline-none focus:border-gold/50"
                    />
                  </div>
                </div>
              </div>

              {/* Sticky Footer */}
              <div className="p-4 border-t border-border/60 flex justify-end gap-3 shrink-0 bg-[#0A0B0E]">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card/60"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-6 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] shadow-md transition-all"
                >
                  Save Schedule to MySQL & CRM
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
