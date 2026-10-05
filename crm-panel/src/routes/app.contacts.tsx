import { createFileRoute } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { Search, Plus, Mail, Phone, Contact2, UserRound, Truck, Building2, Camera, Video, X } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { type Contact } from "@/lib/mock-data";
import { fetchContacts, createContact } from "@/lib/api";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { VideoCallModal } from "@/components/VideoCallModal";
import { useContactsQuery } from "@/lib/queries";
import { useQueryClient } from "@tanstack/react-query";

export const Route = createFileRoute("/app/contacts")({
  head: () => ({ meta: [{ title: "Contacts — Next Gen Relocation CRM" }] }),
  component: Contacts,
});

type ExtendedContactType = Contact["type"] | "Surveyor";

const typeIcon: Record<string, typeof UserRound> = {
  Customer: UserRound,
  Surveyor: Camera,
  Driver: Truck,
  Supplier: Building2,
};

function Contacts() {
  const queryClient = useQueryClient();
  const { data: rawContacts = [], isLoading: loading } = useContactsQuery();
  const contactsList = useMemo(() => (Array.isArray(rawContacts) ? rawContacts : []), [rawContacts]);

  const [tab, setTab] = useState<"All" | ExtendedContactType>("All");
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 300);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // State for active video call modal
  const [activeCall, setActiveCall] = useState<{
    open: boolean;
    contactName: string;
    roomUrl: string;
    roomCode: string;
  } | null>(null);

  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    type: "Customer" as ExtendedContactType,
    moves: 1,
    lifetimeValue: 1500,
    status: "Active" as "Active" | "Lead" | "Past",
  });

  const handleCreateContact = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createContact(formData);
      setIsModalOpen(false);
      setFormData({
        name: "",
        email: "",
        phone: "",
        type: "Customer",
        moves: 1,
        lifetimeValue: 1500,
        status: "Active",
      });
      queryClient.invalidateQueries({ queryKey: ["contacts"] });
    } catch (err) {
      console.error("Error creating contact:", err);
    }
  };

  const handleStartCall = async (contact: any) => {
    const targetLeadId = contact.leadId || "L-8855";
    
    // Trigger database ringing state so surveyor's mobile app rings!
    try {
      await fetch(`/api/leads/${targetLeadId}/video-call/start`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
      });
    } catch (e) {
      console.warn("Failed to trigger ringing state on backend:", e);
    }

    const cleanName = (contact.name || "Surveyor").replace(/[^a-zA-Z0-9]/g, "-");
    const roomCode = `NextGen-Call-${targetLeadId}`;
    const roomUrl = `https://vpaas-magic-cookie.8x8.vc/${roomCode}`;

    setActiveCall({
      open: true,
      contactName: contact.name,
      roomUrl,
      roomCode,
    });
  };

  const filtered = useMemo(
    () =>
      contactsList.filter(
        (c: any) => (tab === "All" || c.type === tab) && c.name.toLowerCase().includes(debouncedQ.toLowerCase()),
      ),
    [contactsList, tab, debouncedQ],
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Contact2 className="h-3.5 w-3.5" /> Directory & Surveyors
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Contacts</h1>
          <p className="text-sm text-muted-foreground">Customers, Field Surveyors, Drivers and Suppliers in Next Gen CRM.</p>
        </div>
        <button
          onClick={() => setIsModalOpen(true)}
          className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]"
        >
          <Plus className="h-4 w-4" /> Add Contact
        </button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {(["All", "Customer", "Surveyor", "Driver", "Supplier"] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t as any)}
            className={`rounded-full border px-3.5 py-1.5 text-xs transition-all ${
              tab === t ? "border-gold/50 bg-gold/15 text-gold font-semibold" : "border-border bg-card/40 text-muted-foreground hover:border-gold/30"
            }`}
          >
            {t}
          </button>
        ))}
        <div className="relative ml-auto min-w-[200px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search surveyors or contacts…"
            className="w-full rounded-xl border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none focus:border-gold/50"
          />
        </div>
      </div>

      {filtered.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-border/60 bg-card/20 p-12 text-center">
          <Contact2 className="mx-auto h-10 w-10 text-gold/40" />
          <h3 className="mt-3 font-display text-lg font-semibold text-foreground">No Contacts Found</h3>
          <p className="mt-1 text-xs text-muted-foreground">
            {q ? "No contacts match your search filter." : "No contacts found in database. New surveyors and leads will automatically list here."}
          </p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {filtered.map((c: any) => {
            const Icon = typeIcon[c.type] || UserRound;
            const isSurveyor = c.type === "Surveyor";
            return (
              <GlassCard key={c.id} hover className="p-5 flex flex-col justify-between">
                <div>
                  <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                      <div className={`flex h-11 w-11 items-center justify-center rounded-full border text-sm font-semibold ${isSurveyor ? "border-amber-500/40 bg-amber-500/10 text-amber-400" : "border-gold/30 bg-gold/10 text-gold"}`}>
                        {c.name ? c.name.split(" ").map((n: string) => n[0]).join("") : "C"}
                      </div>
                      <div>
                        <div className="font-medium flex items-center gap-1.5">
                          {c.name}
                          {isSurveyor && <span className="rounded bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-400">SURVEYOR</span>}
                        </div>
                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                          <Icon className="h-3 w-3" /> {c.type}
                        </div>
                      </div>
                    </div>
                    <span className={`rounded-md px-2 py-0.5 text-[10px] ${c.status === "Active" ? "bg-success/15 text-success" : "bg-muted/40 text-muted-foreground"}`}>
                      {c.status}
                    </span>
                  </div>
                  <div className="mt-4 space-y-2 text-xs text-muted-foreground">
                    <a
                      href={`mailto:${c.email}`}
                      className="flex items-center gap-2 rounded-lg bg-secondary/50 px-2.5 py-1.5 transition-colors hover:bg-gold/15 hover:text-gold"
                    >
                      <Mail className="h-3.5 w-3.5" /> <span className="truncate">{c.email}</span>
                    </a>
                    <a
                      href={`tel:${c.phone}`}
                      className="flex items-center gap-2 rounded-lg bg-secondary/50 px-2.5 py-1.5 transition-colors hover:bg-gold/15 hover:text-gold"
                    >
                      <Phone className="h-3.5 w-3.5" /> <span className="truncate">{c.phone}</span>
                    </a>
                  </div>
                </div>

                <div className="mt-4 flex items-center justify-between border-t border-border/50 pt-3 text-xs">
                  {c.type === "Customer" ? (
                    <>
                      <span className="text-muted-foreground">{c.moves} move(s)</span>
                      <button
                        onClick={() => handleStartCall(c)}
                        className="flex items-center gap-1 rounded-lg border border-gold/30 bg-gold/10 px-2.5 py-1 text-xs font-semibold text-gold hover:bg-gold/20 transition-all"
                      >
                        <Video className="h-3.5 w-3.5" /> Video Call
                      </button>
                    </>
                  ) : isSurveyor ? (
                    <>
                      <span className="text-amber-400 font-semibold text-[11px]">Field Inspector</span>
                      <button
                        onClick={() => handleStartCall(c)}
                        className="flex items-center gap-1.5 rounded-xl border border-amber-500/40 bg-amber-500/15 px-3 py-1.5 text-xs font-bold text-amber-400 hover:bg-amber-500/25 shadow-sm transition-all"
                      >
                        <Video className="h-3.5 w-3.5 animate-pulse text-amber-400" /> Start Video Call
                      </button>
                    </>
                  ) : (
                    <span className="text-muted-foreground">Verified Directory Contact</span>
                  )}
                </div>
              </GlassCard>
            );
          })}
        </div>
      )}

      {/* Video Call Modal */}
      {activeCall?.open && (
        <VideoCallModal
          open={activeCall.open}
          onClose={() => setActiveCall(null)}
          roomUrl={activeCall.roomUrl}
          roomCode={activeCall.roomCode}
          leadName={activeCall.contactName}
        />
      )}

      {/* Add Contact Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150">
            <button onClick={() => setIsModalOpen(false)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>
            <h2 className="font-display text-2xl font-semibold mb-1">Add Contact</h2>
            <p className="text-xs text-muted-foreground mb-4">Save a new surveyor or contact in Next Gen CRM.</p>
            <form onSubmit={handleCreateContact} className="space-y-4">
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Full Name</label>
                <input
                  required
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="e.g. David Miller"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Email</label>
                  <input
                    required
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder="david@example.com"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Phone</label>
                  <input
                    required
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    placeholder="+44 7700 900888"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Contact Type</label>
                  <select
                    value={formData.type}
                    onChange={(e) => setFormData({ ...formData, type: e.target.value as ExtendedContactType })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
                  >
                    <option value="Customer" className="bg-card text-foreground">Customer</option>
                    <option value="Surveyor" className="bg-card text-foreground">Surveyor</option>
                    <option value="Driver" className="bg-card text-foreground">Driver</option>
                    <option value="Supplier" className="bg-card text-foreground">Supplier</option>
                  </select>
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Status</label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as any })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
                  >
                    <option value="Active" className="bg-card text-foreground">Active</option>
                    <option value="Lead" className="bg-card text-foreground">Lead</option>
                    <option value="Past" className="bg-card text-foreground">Past</option>
                  </select>
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
                  Save Contact
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
