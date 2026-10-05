import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { useConfirm } from "@/contexts/ConfirmContext";
import { FileText, Plus, Search, Filter, CheckCircle2, Clock, Send, Eye, X, Trash2, Pencil, RefreshCcw } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchQuotations, updateQuotation, sendQuotation, deleteQuotation } from "@/lib/api";

export const Route = createFileRoute("/app/quotations")({
  head: () => ({ meta: [{ title: "Quotations — Next Gen CRM" }] }),
  component: QuotationsPage,
});

export interface QuotationItem {
  label: string;
  qty: number;
  unit_price: number;
  amount: number;
}

export interface QuotationPackage {
  name: string;
  description?: string;
  total: number;
}

export interface Quotation {
  id: string;
  leadId?: string;
  quoteType?: "indicative" | "post-survey";
  quoteNumber: string;
  clientName: string;
  clientEmail: string;
  moveType: string;
  from?: string;
  to?: string;
  moveDate?: string;
  items: QuotationItem[];
  packages?: QuotationPackage[];
  selectedPackage?: string;
  subtotal: number;
  tax: number;
  total: number;
  depositPercent?: number;
  depositAmount?: number;
  paymentOption?: string;
  initialDepositPaid?: boolean;
  notes?: string;
  status: "draft" | "sent" | "approved" | "declined";
  validUntil?: string;
  sentAt?: string;
  approvedAt?: string;
  declinedAt?: string;
  createdAt?: string;
  invoice?: {
    id: string;
    invoiceNumber: string;
    status: string;
    total: number;
    serviceTitle: string;
  } | null;
}

const statusStyle: Record<string, string> = {
  approved: "bg-success/15 text-success border border-success/30",
  sent: "bg-gold/15 text-gold border border-gold/30",
  declined: "bg-destructive/15 text-destructive border border-destructive/30",
  draft: "bg-muted/40 text-muted-foreground border border-border",
};

const statusLabel: Record<string, string> = {
  draft: "Draft",
  sent: "Sent · Awaiting Approval",
  approved: "Approved",
  declined: "Declined",
};

import { useQuotationsQuery } from "@/lib/queries";
import { useQueryClient } from "@tanstack/react-query";

function QuotationsPage() {
  const confirm = useConfirm();
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState("");
  const debouncedSearch = useDebouncedValue(searchQuery, 300);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [selectedQuotation, setSelectedQuotation] = useState<Quotation | null>(null);
  const [editing, setEditing] = useState<Quotation | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const { data: rawQuotations = [], isLoading: loading, refetch, isRefetching } = useQuotationsQuery();
  const quotations: Quotation[] = useMemo(() => (Array.isArray(rawQuotations) ? rawQuotations : []), [rawQuotations]);

  const filteredQuotes = useMemo(() => {
    const q = debouncedSearch.toLowerCase();
    return quotations.filter(
      (x) =>
        x.clientName?.toLowerCase().includes(q) ||
        x.quoteNumber?.toLowerCase().includes(q) ||
        (x.moveType || "").toLowerCase().includes(q),
    );
  }, [quotations, debouncedSearch]);

  const totalVal = useMemo(() => quotations.reduce((a, q) => a + Number(q.total || 0), 0), [quotations]);
  const acceptedVal = useMemo(
    () => quotations.filter((q) => q.status === "approved").reduce((a, q) => a + Number(q.total || 0), 0),
    [quotations],
  );
  const pendingCount = useMemo(
    () => quotations.filter((q) => q.status === "sent" || q.status === "draft").length,
    [quotations],
  );
  const conversion = quotations.length
    ? `${Math.round((quotations.filter((q) => q.status === "approved").length / quotations.length) * 100)}%`
    : "0%";

  const handleSend = async (q: Quotation) => {
    setBusyId(q.id);
    try {
      await sendQuotation(q.id);
      setSelectedQuotation(null);
      queryClient.invalidateQueries({ queryKey: ["quotations"] });
    } catch (err) {
      console.error("Failed to send quotation:", err);
      toast.error(err instanceof Error ? err.message : "Failed to send quotation");
    } finally {
      setBusyId(null);
    }
  };

  const handleDelete = async (q: Quotation) => {
    if (!await confirm(`Are you sure you want to delete quotation ${q.quoteNumber}?`)) return;
    setBusyId(q.id);
    try {
      await deleteQuotation(q.id);
      if (selectedQuotation?.id === q.id) setSelectedQuotation(null);
      queryClient.invalidateQueries({ queryKey: ["quotations"] });
    } catch (err) {
      console.error("Failed to delete quotation:", err);
      toast.error(err instanceof Error ? err.message : "Failed to delete quotation");
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
            <FileText className="h-3.5 w-3.5" /> Quotation Management
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Quotations & Estimates</h1>
          <p className="text-sm text-muted-foreground">
            Indicative quotes are generated automatically on lead approval. Edit, then send with a customer approve link.
          </p>
        </div>
      </div>

      {/* Metrics Cards */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Total Quotations</span>
            <FileText className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">{quotations.length}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Valued at £{totalVal.toLocaleString()}</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Approved Value</span>
            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-success">£{acceptedVal.toLocaleString()}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Converted to confirmed jobs</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Pending Response</span>
            <Clock className="h-3.5 w-3.5 text-warning" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-warning">{pendingCount}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Draft or awaiting client decision</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Conversion Rate</span>
            <Send className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">{conversion}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Approved vs total issued</div>
        </GlassCard>
      </div>

      {/* Search & Filter Bar */}
      <GlassCard className="p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by quote number, client, or move type..."
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
            <option value="draft" className="bg-card">Draft</option>
            <option value="sent" className="bg-card">Sent</option>
            <option value="approved" className="bg-card">Approved</option>
            <option value="declined" className="bg-card">Declined</option>
          </select>
        </div>
      </GlassCard>

      {/* Quotations List */}
      <GlassCard className="overflow-hidden">
        <div className="border-b border-border/60 p-5 flex items-center justify-between">
          <h2 className="font-display text-xl font-semibold">Quotations Directory</h2>
          <span className="text-xs text-muted-foreground">{filteredQuotes.length} quotes listed</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
              <tr>
                <th className="px-5 py-3">Quote Ref</th>
                <th className="px-5 py-3">Client</th>
                <th className="px-5 py-3">Move Type</th>
                <th className="px-5 py-3">Valid Until</th>
                <th className="px-5 py-3">Amount</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/40">
              {filteredQuotes.map((q) => (
                <tr key={q.id} className="hover:bg-sidebar-accent/50 transition-colors">
                  <td className="px-5 py-4">
                    <div className="font-mono font-medium text-gold">{q.quoteNumber}</div>
                    {q.quoteType && (
                      <span className={`inline-block mt-0.5 text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wider ${
                        q.quoteType === "post-survey" ? "bg-purple-500/20 text-purple-400 border border-purple-500/30" : "bg-blue-500/20 text-blue-400 border border-blue-500/30"
                      }`}>
                        {q.quoteType === "post-survey" ? "Post-Survey" : "Indicative"}
                      </span>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <div className="font-medium text-foreground">{q.clientName}</div>
                    <div className="text-xs text-muted-foreground">{q.clientEmail}</div>
                  </td>
                  <td className="px-5 py-4 text-muted-foreground">{q.moveType}</td>
                  <td className="px-5 py-4 text-xs">{q.validUntil || "—"}</td>
                  <td className="px-5 py-4">
                    <div className="font-semibold text-foreground">£{Number(q.total).toLocaleString()}</div>
                    <div className="text-[11px] text-gold font-mono">
                      20% Dep: £{Number(q.depositAmount || q.total * 0.2).toFixed(2)}
                    </div>
                    {q.initialDepositPaid ? (
                      <span className="inline-block mt-0.5 text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        20% Paid ✓
                      </span>
                    ) : q.paymentOption === "pay_later" ? (
                      <span className="inline-block mt-0.5 text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider bg-blue-500/20 text-blue-400 border border-blue-500/30">
                        Pay Later
                      </span>
                    ) : null}
                  </td>
                  <td className="px-5 py-4">
                    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${statusStyle[q.status] || statusStyle.draft}`}>
                      {statusLabel[q.status] || q.status}
                    </span>
                    {q.invoice && (
                      <a
                        href={`/app/invoices?search=${q.invoice.invoiceNumber}`}
                        className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-success hover:underline bg-success/10 border border-success/30 px-2 py-0.5 rounded-md w-fit"
                        title="View generated invoice"
                      >
                        💳 {q.invoice.invoiceNumber} ({q.invoice.status})
                      </a>
                    )}
                  </td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => setEditing({ ...q, items: [...(q.items || [])] })}
                        className="inline-flex items-center gap-1 rounded-lg border border-border bg-card/60 px-2.5 py-1.5 text-xs text-muted-foreground hover:border-gold/50 hover:text-gold transition-colors"
                        title="Edit quotation items or price"
                      >
                        <Pencil className="h-3.5 w-3.5" /> Edit
                      </button>

                      <button
                        disabled={busyId === q.id}
                        onClick={() => handleSend(q)}
                        className="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-gold-bright to-gold-dim px-2.5 py-1.5 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50"
                        title={q.status === "sent" ? "Resend quotation email to client" : "Send quotation email"}
                      >
                        <Send className="h-3.5 w-3.5" /> {busyId === q.id ? "Sending…" : q.status === "sent" ? "Resend" : "Send"}
                      </button>

                      <button
                        disabled={busyId === q.id}
                        onClick={() => handleDelete(q)}
                        className="inline-flex items-center gap-1 rounded-lg border border-destructive/30 bg-destructive/10 px-2.5 py-1.5 text-xs text-destructive hover:bg-destructive/20 transition-colors"
                        title="Delete quotation"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>

                      <button
                        onClick={() => setSelectedQuotation(q)}
                        className="inline-flex items-center gap-1 rounded-lg border border-border bg-card/60 px-2.5 py-1.5 text-xs text-muted-foreground hover:border-gold/50 hover:text-gold transition-colors"
                      >
                        <Eye className="h-3.5 w-3.5" /> View
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filteredQuotes.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-sm text-muted-foreground">
                    {loading ? "Loading quotations…" : "No quotations yet. They are created automatically when a lead is approved."}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </GlassCard>

      {/* View Quotation Modal */}
      {selectedQuotation && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-2xl rounded-2xl p-6 relative animate-in fade-in zoom-in duration-150 space-y-6 max-h-[90vh] overflow-y-auto">
            <button onClick={() => setSelectedQuotation(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-start justify-between border-b border-border/50 pb-4">
              <div>
                <span className="text-xs uppercase tracking-widest text-gold font-semibold">{selectedQuotation.quoteNumber}</span>
                <h2 className="font-display text-2xl font-semibold mt-1">{selectedQuotation.moveType}</h2>
                <p className="text-xs text-muted-foreground">Issued to {selectedQuotation.clientName} ({selectedQuotation.clientEmail})</p>
              </div>
              <div className="text-right">
                <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusStyle[selectedQuotation.status] || statusStyle.draft}`}>
                  {statusLabel[selectedQuotation.status] || selectedQuotation.status}
                </span>
                <div className="text-xs text-muted-foreground mt-1">Valid Until: {selectedQuotation.validUntil || "—"}</div>
              </div>
            </div>

            {selectedQuotation.invoice && (
              <div className="rounded-xl border border-success/40 bg-success/10 p-3.5 flex items-center justify-between">
                <div>
                  <div className="text-xs font-semibold uppercase tracking-wider text-success">Generated Deposit Tax Invoice</div>
                  <div className="text-sm font-semibold text-foreground mt-0.5">
                    #{selectedQuotation.invoice.invoiceNumber} — {selectedQuotation.invoice.serviceTitle}
                  </div>
                  <div className="text-xs text-muted-foreground">Amount: £{Number(selectedQuotation.invoice.total).toLocaleString()} • Status: <strong className="text-success">{selectedQuotation.invoice.status}</strong></div>
                </div>
                <a
                  href={`/app/invoices?search=${selectedQuotation.invoice.invoiceNumber}`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-success text-black font-bold px-3 py-1.5 text-xs hover:bg-success/90 transition-colors"
                >
                  View Invoice ➔
                </a>
              </div>
            )}

            <div className="pt-2">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-gold mb-2">Cost Breakdown</h3>
              <div className="rounded-xl border border-border/60 overflow-hidden divide-y divide-border/40 mb-4">
                {(selectedQuotation.items || []).map((item, idx) => (
                  <div key={idx} className="flex items-center justify-between p-3 text-sm">
                    <div>
                      <div className="font-medium text-foreground">{item.label}</div>
                      <div className="text-xs text-muted-foreground">Qty: {item.qty} × £{Number(item.unit_price).toLocaleString()}</div>
                    </div>
                    <div className="font-semibold text-foreground">£{Number(item.amount).toLocaleString()}</div>
                  </div>
                ))}
                <div className="flex items-center justify-between p-3 text-xs text-muted-foreground">
                  <span>Subtotal</span><span>£{Number(selectedQuotation.subtotal).toLocaleString()}</span>
                </div>
                <div className="flex items-center justify-between p-3 text-xs text-muted-foreground">
                  <span>VAT</span><span>£{Number(selectedQuotation.tax).toLocaleString()}</span>
                </div>
                <div className="flex items-center justify-between p-3 bg-muted/20 font-semibold text-lg">
                  <span>Grand Total (Indicative)</span>
                  <span className="text-gold">£{Number(selectedQuotation.total).toLocaleString()}</span>
                </div>
              </div>

              {selectedQuotation.quoteType === "post-survey" && (
                <>
                  <h3 className="text-xs font-semibold uppercase tracking-wider text-gold mb-2 mt-4">Pricing Plans (Client Selects One)</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    {(selectedQuotation.packages && selectedQuotation.packages.length > 0 ? selectedQuotation.packages : []).map((pkg, idx) => (
                      <div key={idx} className={`rounded-xl border p-3 ${selectedQuotation.selectedPackage === pkg.name ? 'border-success bg-success/10' : 'border-border/60 bg-muted/10'}`}>
                        <div className="flex justify-between items-center mb-1">
                          <strong className="text-sm text-foreground">{pkg.name}</strong>
                          {selectedQuotation.selectedPackage === pkg.name && <CheckCircle2 className="h-4 w-4 text-success" />}
                        </div>
                        <div className="text-xs text-muted-foreground mb-2">{pkg.description}</div>
                        <div className="font-semibold text-gold text-lg">£{Number(pkg.total).toLocaleString()}</div>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>

            {selectedQuotation.notes && (
              <div className="space-y-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Quotation Notes & Details</h3>
                <div className="rounded-xl border border-border/60 bg-muted/10 p-4 text-xs text-muted-foreground leading-relaxed whitespace-pre-wrap">
                  {selectedQuotation.notes}
                </div>
              </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
              <div className="flex flex-wrap items-center gap-2">
                <a
                  href={`/quotation/approve?quote_id=${selectedQuotation.id}`}
                  target="_blank"
                  rel="noreferrer"
                  className="flex items-center gap-1.5 rounded-xl border border-gold/40 bg-gold/10 px-3.5 py-2 text-xs font-semibold text-gold hover:bg-gold/20"
                >
                  🔗 Client Approval Portal
                </a>

                <button
                  onClick={() => {
                    setEditing({ ...selectedQuotation, items: [...(selectedQuotation.items || [])] });
                    setSelectedQuotation(null);
                  }}
                  className="flex items-center gap-1.5 rounded-xl border border-border px-3.5 py-2 text-xs font-semibold text-muted-foreground hover:border-gold/50 hover:text-gold"
                >
                  <Pencil className="h-4 w-4" /> Edit Quotation
                </button>

                <button
                  disabled={busyId === selectedQuotation.id}
                  onClick={() => handleSend(selectedQuotation)}
                  className="flex items-center gap-1.5 rounded-xl bg-gold/20 border border-gold/30 px-3.5 py-2 text-xs font-semibold text-gold hover:bg-gold/30 disabled:opacity-50"
                >
                  <Send className="h-4 w-4" /> {selectedQuotation.status === "sent" ? "Resend to Client" : "Send to Client"}
                </button>

                <button
                  disabled={busyId === selectedQuotation.id}
                  onClick={() => handleDelete(selectedQuotation)}
                  className="flex items-center gap-1.5 rounded-xl border border-destructive/30 bg-destructive/10 px-3.5 py-2 text-xs font-semibold text-destructive hover:bg-destructive/20 disabled:opacity-50"
                >
                  <Trash2 className="h-4 w-4" /> Delete
                </button>
              </div>
              <button
                onClick={() => setSelectedQuotation(null)}
                className="rounded-xl border border-border px-4 py-2 text-xs text-muted-foreground hover:bg-card ml-auto"
              >
                Close Window
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Edit Quotation Modal */}
      {editing && (
        <EditQuotationModal
          quote={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            queryClient.invalidateQueries({ queryKey: ["quotations"] });
          }}
        />
      )}
    </div>
  );
}

function EditQuotationModal({
  quote,
  onClose,
  onSaved,
}: {
  quote: Quotation;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [clientName, setClientName] = useState(quote.clientName || "");
  const [clientEmail, setClientEmail] = useState(quote.clientEmail || "");
  const [moveType, setMoveType] = useState(quote.moveType || "");
  const [validUntil, setValidUntil] = useState(quote.validUntil || "");
  const [notes, setNotes] = useState(quote.notes || "");
  const [items, setItems] = useState<QuotationItem[]>(
    (quote.items || []).map((i) => ({ ...i })),
  );
  const [packages, setPackages] = useState<QuotationPackage[]>(
    (quote.packages && quote.packages.length > 0) ? (quote.packages || []).map((p) => ({ ...p })) : [
      { name: "Basic", description: "Standard vehicle and team, loading/unloading only.", total: quote.total || 0 },
      { name: "Standard", description: "Includes dismantling/reassembling basic furniture.", total: (quote.total || 0) + 150 },
      { name: "Premium", description: "Full packing service, premium materials, and assembly.", total: (quote.total || 0) + 400 }
    ]
  );
  const [saving, setSaving] = useState(false);

  const subtotal = useMemo(
    () => items.reduce((s, i) => s + Number(i.qty) * Number(i.unit_price), 0),
    [items],
  );
  const tax = useMemo(() => Math.round(subtotal * 0.2 * 100) / 100, [subtotal]);
  const grandTotal = subtotal + tax;

  const setItem = (idx: number, field: keyof QuotationItem, value: any) => {
    setItems((prev) => {
      const next = [...prev];
      const it = { ...next[idx], [field]: value };
      it.amount = Number(it.qty) * Number(it.unit_price);
      next[idx] = it;
      return next;
    });
  };

  const addItem = () => setItems((p) => [...p, { label: "", qty: 1, unit_price: 0, amount: 0 }]);
  const removeItem = (idx: number) => setItems((p) => p.filter((_, i) => i !== idx));

  const setPackageField = (idx: number, field: keyof QuotationPackage, value: any) => {
    setPackages((prev) => {
      const next = [...prev];
      next[idx] = { ...next[idx], [field]: value };
      return next;
    });
  };

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await updateQuotation(quote.id, {
        clientName,
        clientEmail,
        moveType,
        validUntil: validUntil || undefined,
        notes,
        items: items.map((i) => ({
          label: i.label,
          qty: Number(i.qty),
          unit_price: Number(i.unit_price),
          amount: Number(i.qty) * Number(i.unit_price),
        })),
        packages: quote.quoteType === "post-survey" ? packages : undefined,
      });
      onSaved();
    } catch (err) {
      console.error("Failed to save quotation:", err);
      toast.error(err instanceof Error ? err.message : "Failed to save quotation");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
      <div className="glass-card w-full max-w-2xl rounded-2xl p-6 relative max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in duration-150">
        <button onClick={onClose} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
          <X className="h-5 w-5" />
        </button>

        <span className="text-xs uppercase tracking-widest text-gold font-semibold">{quote.quoteNumber}</span>
        <h2 className="font-display text-2xl font-semibold mb-1 mt-1">Edit Quotation</h2>
        <p className="text-xs text-muted-foreground mb-6">Adjust the indicative quote before sending it to the customer.</p>

        <form onSubmit={save} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Client Name</label>
              <input required value={clientName} onChange={(e) => setClientName(e.target.value)} className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Client Email</label>
              <input required type="email" value={clientEmail} onChange={(e) => setClientEmail(e.target.value)} className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Move Type</label>
              <input value={moveType} onChange={(e) => setMoveType(e.target.value)} className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50" />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Valid Until</label>
              <input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground" />
            </div>
          </div>

          {/* Line Items */}
          <div className="pt-2 space-y-3">
            <div className="flex items-center justify-between">
              <label className="text-xs font-semibold uppercase tracking-wider text-gold">Line Items</label>
              <button type="button" onClick={addItem} className="text-xs text-gold hover:underline flex items-center gap-1">
                <Plus className="h-3.5 w-3.5" /> Add Line Item
              </button>
            </div>

            {items.map((item, index) => (
              <div key={index} className="grid grid-cols-12 gap-2 items-center bg-input/20 p-2.5 rounded-xl border border-border/40">
                <div className="col-span-6">
                  <input required placeholder="Item Description" value={item.label} onChange={(e) => setItem(index, "label", e.target.value)} className="w-full rounded-lg border border-border bg-input/40 px-2.5 py-1.5 text-xs outline-none focus:border-gold/50" />
                </div>
                <div className="col-span-2">
                  <input type="number" min="1" placeholder="Qty" value={item.qty} onChange={(e) => setItem(index, "qty", Number(e.target.value))} className="w-full rounded-lg border border-border bg-input/40 px-2 py-1.5 text-xs outline-none focus:border-gold/50" />
                </div>
                <div className="col-span-3">
                  <input type="number" placeholder="Price (£)" value={item.unit_price} onChange={(e) => setItem(index, "unit_price", Number(e.target.value))} className="w-full rounded-lg border border-border bg-input/40 px-2 py-1.5 text-xs outline-none focus:border-gold/50" />
                </div>
                <div className="col-span-1 text-center">
                  {items.length > 1 && (
                    <button type="button" onClick={() => removeItem(index)} className="text-destructive hover:opacity-80">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div>
            <label className="mb-1 block text-xs text-muted-foreground">Notes</label>
            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 resize-none" />
          </div>

          <div className="rounded-xl border border-border/60 bg-muted/10 p-3 text-sm space-y-1">
            <div className="flex justify-between text-xs text-muted-foreground"><span>Subtotal</span><span>£{subtotal.toLocaleString()}</span></div>
            <div className="flex justify-between text-xs text-muted-foreground"><span>VAT (20%)</span><span>£{tax.toLocaleString()}</span></div>
            <div className="flex justify-between font-semibold text-gold"><span>Indicative Total</span><span>£{grandTotal.toLocaleString()}</span></div>
          </div>

          {/* Pricing Plans for Post-Survey */}
          {quote.quoteType === "post-survey" && (
            <div className="pt-4 border-t border-border/50">
              <label className="text-xs font-semibold uppercase tracking-wider text-gold block mb-3">Pricing Plans (Client Selects One)</label>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                {packages.map((pkg, index) => (
                  <div key={index} className="rounded-xl border border-border bg-input/40 p-3 space-y-2">
                    <div className="font-semibold text-foreground text-sm">{pkg.name} Plan</div>
                    <textarea 
                      placeholder="Description of what is included" 
                      value={pkg.description} 
                      onChange={(e) => setPackageField(index, "description", e.target.value)} 
                      rows={3} 
                      className="w-full rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs outline-none focus:border-gold/50 resize-none" 
                    />
                    <div className="relative">
                      <span className="absolute left-2.5 top-1.5 text-muted-foreground text-xs">£</span>
                      <input 
                        type="number" 
                        placeholder="Total Price" 
                        value={pkg.total} 
                        onChange={(e) => setPackageField(index, "total", Number(e.target.value))} 
                        className="w-full rounded-lg border border-border bg-background py-1.5 pl-6 pr-2.5 text-xs font-semibold outline-none focus:border-gold/50 text-gold" 
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="flex justify-end gap-3 pt-2 border-t border-border/50 mt-4">
            <button type="button" onClick={onClose} className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card">
              Cancel
            </button>
            <button type="submit" disabled={saving} className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50">
              {saving ? "Saving…" : "Save Changes"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
