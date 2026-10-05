import { createFileRoute } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { Receipt, Plus, Search, Filter, CheckCircle2, AlertTriangle, Clock, Eye, Download, X, CreditCard, RefreshCw, Banknote, Landmark } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { useInvoicesQuery, useUpdateInvoiceMutation, useSendInvoiceMutation } from "@/lib/queries";
import { createInvoice, recordInvoicePayment } from "@/lib/api";

export const Route = createFileRoute("/app/invoices")({
  head: () => ({ meta: [{ title: "Invoices — Next Gen CRM" }] }),
  component: InvoicesPage,
});

export interface InvoiceItem {
  description?: string;
  label?: string;
  quantity?: number;
  qty?: number;
  unitPrice?: number;
  unit_price?: number;
  amount?: number;
}

export interface Invoice {
  id: string;
  invoiceNumber: string;
  clientName: string;
  clientEmail: string;
  clientPhone?: string;
  serviceTitle: string;
  invoiceType?: string;
  status: "Paid" | "Unpaid" | "Overdue" | "Draft" | "paid" | "draft" | "unpaid";
  issueDate: string;
  dueDate: string;
  items: InvoiceItem[];
  subtotal: number;
  tax: number;
  total: number;
  paidAmount?: number;
  balanceDue?: number;
  paymentMethod?: string;
  paymentReference?: string;
  paidAt?: string;
  paymentDate?: string;
  notes?: string;
}

function InvoicesPage() {
  const { data: rawInvoices = [], isLoading, refetch, isFetching } = useInvoicesQuery();
  const [invoices, setInvoices] = useState<Invoice[]>([]);

  useEffect(() => {
    if (Array.isArray(rawInvoices)) {
      setInvoices(rawInvoices);
    }
  }, [rawInvoices]);

  const updateInvoice = useUpdateInvoiceMutation();
  const sendInvoice = useSendInvoiceMutation();

  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);

  // New Invoice Form State
  const [newInvoice, setNewInvoice] = useState({
    clientName: "",
    clientEmail: "",
    serviceTitle: "Relocation Service",
    dueDays: 14,
    items: [{ description: "Relocation Service Charges", quantity: 1, unitPrice: 650 }],
  });

  const filteredInvoices = invoices.filter((inv) => {
    const name = inv.clientName || (inv as any).client_name || "";
    const num = inv.invoiceNumber || (inv as any).invoice_number || "";
    const service = inv.serviceTitle || (inv as any).service_title || "";
    const status = inv.status || "";
    const matchesSearch =
      name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      num.toLowerCase().includes(searchQuery.toLowerCase()) ||
      service.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesStatus = statusFilter === "all" || status.toLowerCase() === statusFilter.toLowerCase();
    return matchesSearch && matchesStatus;
  });

  const totalInvoiced = invoices.reduce((sum, i) => sum + i.total, 0);
  const paidVal = invoices.filter((i) => i.status?.toLowerCase() === "paid").reduce((sum, i) => sum + i.total, 0);
  const unpaidVal = invoices.filter((i) => ["unpaid", "overdue", "draft"].includes(i.status?.toLowerCase() || "")).reduce((sum, i) => sum + i.total, 0);
  const overdueCount = invoices.filter((i) => i.status?.toLowerCase() === "overdue").length;

  const handleAddItem = () => {
    setNewInvoice({
      ...newInvoice,
      items: [...newInvoice.items, { description: "", quantity: 1, unitPrice: 0 }],
    });
  };

  const handleRemoveItem = (index: number) => {
    setNewInvoice({
      ...newInvoice,
      items: newInvoice.items.filter((_, i) => i !== index),
    });
  };

  const handleItemChange = (index: number, field: keyof InvoiceItem, value: any) => {
    const updatedItems = [...newInvoice.items];
    updatedItems[index] = { ...updatedItems[index], [field]: value };
    setNewInvoice({ ...newInvoice, items: updatedItems });
  };

  const queryClient = useQueryClient();
  const [paymentInvoice, setPaymentInvoice] = useState<Invoice | null>(null);
  const [payMethod, setPayMethod] = useState<"cash" | "bank_transfer" | "stripe">("cash");
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payReference, setPayReference] = useState<string>("");
  const [payDate, setPayDate] = useState<string>(new Date().toISOString().split("T")[0]);
  const [isRecordingPayment, setIsRecordingPayment] = useState<boolean>(false);
  const [isCreatingInvoice, setIsCreatingInvoice] = useState<boolean>(false);

  const handleCreateInvoice = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsCreatingInvoice(true);
    try {
      const dueDateStr = new Date(Date.now() + newInvoice.dueDays * 86400000).toISOString().split("T")[0];
      await createInvoice({
        client_name: newInvoice.clientName,
        client_email: newInvoice.clientEmail,
        service_title: newInvoice.serviceTitle,
        due_date: dueDateStr,
        items: newInvoice.items,
      });

      toast.success("Invoice generated & saved to database successfully!");
      setIsCreateModalOpen(false);
      refetch();
      queryClient.invalidateQueries({ queryKey: ["invoices"] });
      queryClient.invalidateQueries({ queryKey: ["finance"] });

      setNewInvoice({
        clientName: "",
        clientEmail: "",
        serviceTitle: "Relocation Service",
        dueDays: 14,
        items: [{ description: "Relocation Service Charges", quantity: 1, unitPrice: 650 }],
      });
    } catch (err: any) {
      toast.error(err.message || "Failed to create invoice");
    } finally {
      setIsCreatingInvoice(false);
    }
  };

  const handleOpenRecordPayment = (inv: Invoice) => {
    setPaymentInvoice(inv);
    setPayMethod((inv.paymentMethod as any) === "bank_transfer" ? "bank_transfer" : ((inv.paymentMethod as any) === "stripe" ? "stripe" : "cash"));
    setPayAmount(Number(inv.balanceDue || inv.total || 0));
    setPayReference(inv.paymentMethod === "cash" ? "Cash collected by staff" : (inv.paymentReference || "Payment settled"));
    setPayDate(new Date().toISOString().split("T")[0]);
  };

  const handleSubmitRecordPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!paymentInvoice) return;
    setIsRecordingPayment(true);
    try {
      await recordInvoicePayment(paymentInvoice.id, {
        amount: payAmount,
        payment_method: payMethod,
        reference: payReference,
        date: payDate,
      });

      const methodLabel = payMethod === "cash" ? "Cash" : (payMethod === "bank_transfer" ? "Bank Transfer" : "Stripe");
      toast.success(`Payment of £${Number(payAmount).toFixed(2)} recorded via ${methodLabel}! Synced to General Ledger.`);
      setPaymentInvoice(null);
      if (selectedInvoice && selectedInvoice.id === paymentInvoice.id) {
        setSelectedInvoice(null);
      }
      refetch();
      queryClient.invalidateQueries({ queryKey: ["invoices"] });
      queryClient.invalidateQueries({ queryKey: ["finance"] });
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    } catch (err: any) {
      toast.error(err.message || "Failed to record payment");
    } finally {
      setIsRecordingPayment(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Receipt className="h-3.5 w-3.5" /> Billing & Invoicing
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Client Invoices</h1>
          <p className="text-sm text-muted-foreground">Track payments, issue tax invoices, and monitor cashflow.</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => {
              refetch();
              toast.success("Invoices synchronized with ledger");
            }}
            disabled={isFetching}
            className="flex items-center gap-2 rounded-xl border border-border bg-card/60 px-4 py-2.5 text-sm font-semibold text-foreground transition-all hover:border-gold/50 hover:bg-card disabled:opacity-50"
          >
            <RefreshCw className={`h-4 w-4 text-gold ${isFetching ? "animate-spin" : ""}`} />
            {isFetching ? "Syncing..." : "Sync Invoices"}
          </button>
          <button
            onClick={() => setIsCreateModalOpen(true)}
            className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]"
          >
            <Plus className="h-4 w-4" /> Create Invoice
          </button>
        </div>
      </div>

      {/* Metrics Cards */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Total Invoiced</span>
            <Receipt className="h-3.5 w-3.5 text-gold" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-foreground">£{totalInvoiced.toLocaleString()}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">{invoices.length} invoices generated</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Collected / Paid</span>
            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-success">£{paidVal.toLocaleString()}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Settled into account</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Outstanding Balance</span>
            <Clock className="h-3.5 w-3.5 text-warning" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-warning">£{unpaidVal.toLocaleString()}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Pending customer payment</div>
        </GlassCard>

        <GlassCard hover className="p-3.5">
          <div className="flex items-center justify-between text-[10px] text-muted-foreground uppercase tracking-wider">
            <span>Overdue Accounts</span>
            <AlertTriangle className="h-3.5 w-3.5 text-destructive" />
          </div>
          <div className="mt-1 font-display text-2xl font-semibold text-destructive">{overdueCount}</div>
          <div className="mt-0.5 text-[11px] text-muted-foreground">Requires immediate follow up</div>
        </GlassCard>
      </div>

      {/* Search & Filters */}
      <GlassCard className="p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search invoice number, client, or job description..."
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
            <option value="paid" className="bg-card">Paid</option>
            <option value="unpaid" className="bg-card">Unpaid</option>
            <option value="overdue" className="bg-card">Overdue</option>
            <option value="draft" className="bg-card">Draft</option>
          </select>
        </div>
      </GlassCard>

      {/* Invoices List */}
      <GlassCard className="overflow-hidden">
        <div className="border-b border-border/60 p-5 flex items-center justify-between">
          <h2 className="font-display text-xl font-semibold">Invoices Ledger</h2>
          <span className="text-xs text-muted-foreground">{filteredInvoices.length} invoices listed</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40">
              <tr>
                <th className="px-5 py-3">Invoice No</th>
                <th className="px-5 py-3">Client</th>
                <th className="px-5 py-3">Service</th>
                <th className="px-5 py-3">Issue Date</th>
                <th className="px-5 py-3">Due Date</th>
                <th className="px-5 py-3">Total (inc VAT)</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/40">
              {filteredInvoices.map((inv) => (
                <tr key={inv.id} className="hover:bg-sidebar-accent/50 transition-colors">
                  <td className="px-5 py-4 font-mono font-medium text-gold">
                    <div>{inv.invoiceNumber}</div>
                    {inv.invoiceType === "deposit" && (
                      <span className="text-[10px] uppercase font-bold text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded border border-amber-400/20">
                        20% Deposit
                      </span>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <div className="font-medium text-foreground">{inv.clientName}</div>
                    <div className="text-xs text-muted-foreground">{inv.clientEmail}</div>
                  </td>
                  <td className="px-5 py-4 text-muted-foreground">{inv.serviceTitle}</td>
                  <td className="px-5 py-4 text-xs">{inv.issueDate}</td>
                  <td className="px-5 py-4 text-xs">{inv.dueDate}</td>
                  <td className="px-5 py-4">
                    <div className="font-semibold text-foreground">£{inv.total.toLocaleString()}</div>
                    {inv.paidAmount && Number(inv.paidAmount) > 0 ? (
                      <div className="text-[11px] text-emerald-400 font-mono">Paid: £{Number(inv.paidAmount).toFixed(2)}</div>
                    ) : null}
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex flex-col gap-1 items-start">
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
                          inv.status?.toLowerCase() === "paid"
                            ? "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"
                            : inv.status?.toLowerCase() === "unpaid"
                            ? "bg-gold/15 text-gold border border-gold/30"
                            : inv.status?.toLowerCase() === "overdue"
                            ? "bg-destructive/15 text-destructive border border-destructive/30"
                            : "bg-muted/40 text-muted-foreground border border-border"
                        }`}
                      >
                        {inv.status ? inv.status.charAt(0).toUpperCase() + inv.status.slice(1) : "Draft"}
                      </span>
                      {inv.paymentMethod && (
                        <span className="text-[10px] text-muted-foreground uppercase font-mono tracking-wider">
                          {inv.paymentMethod === "cash" ? "💵 Cash" : (inv.paymentMethod === "bank_transfer" ? "🏦 Bank" : "💳 Card")}
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-5 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {inv.status?.toLowerCase() !== "paid" && (
                        <button
                          onClick={() => handleOpenRecordPayment(inv)}
                          title="Record or settle payment"
                          className="inline-flex items-center gap-1 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1.5 text-xs text-emerald-400 hover:bg-emerald-500/20 transition-colors"
                        >
                          <CreditCard className="h-3.5 w-3.5" /> Settle
                        </button>
                      )}
                      <button
                        onClick={() => setSelectedInvoice(inv)}
                        className="inline-flex items-center gap-1 rounded-lg border border-border bg-card/60 px-2.5 py-1.5 text-xs text-muted-foreground hover:border-gold/50 hover:text-gold transition-colors"
                      >
                        <Eye className="h-3.5 w-3.5" /> View
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </GlassCard>

      {/* View Invoice Modal */}
      {selectedInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 overflow-y-auto">
          <div className="glass-card w-full max-w-3xl rounded-3xl p-6 md:p-8 relative animate-in fade-in zoom-in duration-150 space-y-6 bg-[#0E1017] border border-gold/30 shadow-2xl my-6">
            {/* Close Button */}
            <button
              onClick={() => setSelectedInvoice(null)}
              className="absolute right-5 top-5 text-muted-foreground hover:text-foreground p-1 rounded-lg border border-border/40 hover:bg-card transition-colors"
            >
              <X className="h-5 w-5" />
            </button>

            {/* Printable Area */}
            <div id="invoice-printable-area" className="space-y-6">
              {/* Header: Company Info + Invoice Title */}
              <div className="flex flex-col sm:flex-row sm:items-start justify-between border-b border-border/60 pb-6 gap-4">
                <div>
                  <div className="text-gold font-display text-xl font-bold tracking-wider uppercase">
                    ✨ Next Gen Relocation Ltd
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">
                    7 Donnington Road, Reading, RG15NE, UK
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Email: support@nextgenrelocation.co.uk | Tel: +44 20 8123 4567
                  </p>
                  <p className="text-[11px] text-muted-foreground/70 font-mono mt-0.5">
                    VAT Reg No: GB 987 6543 21
                  </p>
                </div>
                <div className="sm:text-right">
                  <span className="text-xs font-mono font-bold tracking-widest text-gold uppercase bg-gold/10 border border-gold/30 px-3 py-1 rounded-full inline-block">
                    {selectedInvoice.invoiceNumber}
                  </span>
                  <div className="mt-2">
                    <span
                      className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ${
                        selectedInvoice.status === "Paid"
                          ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"
                          : selectedInvoice.status === "Unpaid"
                          ? "bg-amber-500/20 text-amber-400 border border-amber-500/40"
                          : selectedInvoice.status === "Overdue"
                          ? "bg-rose-500/20 text-rose-400 border border-rose-500/40"
                          : "bg-muted/40 text-muted-foreground border border-border"
                      }`}
                    >
                      {selectedInvoice.status.toUpperCase()}
                    </span>
                  </div>
                </div>
              </div>

              {/* Billed To & Dates Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs rounded-2xl bg-card/40 p-4 border border-border/40">
                <div>
                  <span className="text-[10px] uppercase font-bold tracking-wider text-gold block mb-1">
                    Billed Client Details
                  </span>
                  <div className="font-semibold text-foreground text-sm">
                    {selectedInvoice.clientName}
                  </div>
                  <div className="text-muted-foreground mt-0.5">
                    📧 {selectedInvoice.clientEmail}
                  </div>
                  {selectedInvoice.clientPhone && (
                    <div className="text-muted-foreground">
                      📞 {selectedInvoice.clientPhone}
                    </div>
                  )}
                </div>

                <div className="sm:text-right space-y-1">
                  <span className="text-[10px] uppercase font-bold tracking-wider text-gold block mb-1">
                    Invoice Metadata & Dates
                  </span>
                  <div>
                    <span className="text-muted-foreground">Issue Date: </span>
                    <strong className="text-foreground">{selectedInvoice.issueDate || new Date().toISOString().split("T")[0]}</strong>
                  </div>
                  <div>
                    <span className="text-muted-foreground">Due Date: </span>
                    <strong className="text-gold">{selectedInvoice.dueDate}</strong>
                  </div>
                  <div>
                    <span className="text-muted-foreground">Service Description: </span>
                    <strong className="text-foreground">{selectedInvoice.serviceTitle}</strong>
                  </div>
                </div>
              </div>

              {/* Invoice Line Items Breakdown */}
              <div className="space-y-3">
                <h3 className="text-xs font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                  <Receipt className="h-4 w-4" /> Itemized Charges Breakdown
                </h3>
                <div className="rounded-2xl border border-border/60 overflow-hidden bg-card/20 divide-y divide-border/40">
                  {/* Table Header */}
                  <div className="grid grid-cols-12 gap-2 p-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground bg-muted/20">
                    <div className="col-span-6">Description / Service</div>
                    <div className="col-span-2 text-center">Qty</div>
                    <div className="col-span-2 text-right">Unit Price</div>
                    <div className="col-span-2 text-right">Total</div>
                  </div>

                  {selectedInvoice.items && selectedInvoice.items.length > 0 ? (
                    selectedInvoice.items.map((item, idx) => {
                      const desc = item.description || item.label || selectedInvoice.serviceTitle || "Relocation Service";
                      const qty = Number(item.quantity ?? item.qty ?? 1);
                      const unit = item.unitPrice ?? item.unit_price ?? item.amount ?? (selectedInvoice.subtotal || selectedInvoice.total || 0);
                      const lineTotal = item.amount && !item.unitPrice && !item.unit_price ? Number(item.amount) : qty * Number(unit);

                      return (
                        <div key={idx} className="grid grid-cols-12 gap-2 p-3 text-xs items-center">
                          <div className="col-span-6 font-medium text-foreground">{desc}</div>
                          <div className="col-span-2 text-center font-mono">{qty}</div>
                          <div className="col-span-2 text-right font-mono text-muted-foreground">£{Number(unit).toFixed(2)}</div>
                          <div className="col-span-2 text-right font-mono font-semibold text-foreground">£{Number(lineTotal).toFixed(2)}</div>
                        </div>
                      );
                    })
                  ) : (
                    <div className="grid grid-cols-12 gap-2 p-3 text-xs items-center">
                      <div className="col-span-6 font-medium text-foreground">{selectedInvoice.serviceTitle}</div>
                      <div className="col-span-2 text-center font-mono">1</div>
                      <div className="col-span-2 text-right font-mono text-muted-foreground">£{Number(selectedInvoice.subtotal || selectedInvoice.total).toFixed(2)}</div>
                      <div className="col-span-2 text-right font-mono font-semibold text-foreground">£{Number(selectedInvoice.subtotal || selectedInvoice.total).toFixed(2)}</div>
                    </div>
                  )}

                  {/* Summary Totals */}
                  <div className="p-4 bg-muted/10 space-y-2 text-xs">
                    <div className="flex justify-between text-muted-foreground">
                      <span>Subtotal (excl. Tax)</span>
                      <span className="font-mono">£{Number(selectedInvoice.subtotal || selectedInvoice.total).toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                      <span>VAT / Tax (20%)</span>
                      <span className="font-mono">£{Number(selectedInvoice.tax || 0).toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between font-bold text-sm text-gold pt-2 border-t border-border/40">
                      <span>Total Amount Due</span>
                      <span className="font-mono text-base">£{Number(selectedInvoice.total).toFixed(2)}</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Payment Details & Settlement Section */}
              <div className="rounded-2xl border border-gold/30 bg-gold/5 p-4 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <span className="font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
                    <CreditCard className="h-4 w-4" /> Payment Details & Remittance
                  </span>
                  <span className={`px-2.5 py-0.5 rounded-full font-bold uppercase text-[10px] ${
                    selectedInvoice.status === "Paid"
                      ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"
                      : "bg-amber-500/20 text-amber-400 border border-amber-500/40"
                  }`}>
                    {selectedInvoice.status === "Paid" ? "Payment Received ✅" : "Awaiting Settlement ⏳"}
                  </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1 text-muted-foreground">
                  <div>
                    <span className="block text-[10px] uppercase font-bold text-muted-foreground/70">Bank Account</span>
                    <span className="text-foreground font-mono font-medium">Next Gen Relocation Ltd</span>
                  </div>
                  <div>
                    <span className="block text-[10px] uppercase font-bold text-muted-foreground/70">Sort Code / Acc No</span>
                    <span className="text-foreground font-mono font-medium">20-45-77 / 89345210</span>
                  </div>
                  <div>
                    <span className="block text-[10px] uppercase font-bold text-muted-foreground/70">Payment Reference</span>
                    <span className="text-gold font-mono font-bold">{selectedInvoice.invoiceNumber}</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Action Controls */}
            <div className="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border/50">
              <div className="flex flex-wrap items-center gap-2">
                {selectedInvoice.status !== "Paid" && (
                  <button
                    onClick={() => handleOpenRecordPayment(selectedInvoice)}
                    className="flex items-center gap-1.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 px-4 py-2 text-xs font-bold text-emerald-400 hover:bg-emerald-500/30 transition-all"
                  >
                    <CreditCard className="h-4 w-4" /> Record / Settle Payment
                  </button>
                )}
                <button
                  onClick={() => {
                    const printContent = document.getElementById("invoice-printable-area");
                    if (!printContent) return;
                    const printWindow = window.open("", "_blank");
                    if (!printWindow) return;
                    printWindow.document.write(`
                      <!DOCTYPE html>
                      <html>
                        <head>
                          <title>Invoice ${selectedInvoice.invoiceNumber}</title>
                          <style>
                            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #fff; color: #111; padding: 40px; margin: 0; }
                            .text-gold { color: #8a6d1c; }
                            .text-muted-foreground { color: #666; }
                            .bg-card\\/40 { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
                            .border-b { border-bottom: 1px solid #ddd; }
                            .grid { display: grid; }
                            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                            .grid-cols-12 { display: flex; justify-content: space-between; }
                            .p-3 { padding: 12px; }
                            .p-4 { padding: 16px; }
                            .p-6 { padding: 24px; }
                            .space-y-6 > * + * { margin-top: 24px; }
                            .space-y-3 > * + * { margin-top: 12px; }
                            .space-y-2 > * + * { margin-top: 8px; }
                            .flex { display: flex; }
                            .justify-between { justify-content: space-between; }
                            .items-center { align-items: center; }
                            .font-bold { font-weight: bold; }
                            .font-mono { font-family: monospace; }
                            .text-xs { font-size: 12px; }
                            .text-sm { font-size: 14px; }
                            .text-xl { font-size: 20px; }
                            .text-right { text-align: right; }
                            .text-center { text-align: center; }
                            .rounded-2xl { border-radius: 12px; }
                            .border { border: 1px solid #ddd; }
                            .bg-gold\\/5 { background: #fffdf5; border: 1px solid #e2c269; }
                            @media print { body { padding: 20px; } }
                          </style>
                        </head>
                        <body>
                          ${printContent.innerHTML}
                          <script>
                            window.onload = function() { window.print(); window.close(); };
                          </script>
                        </body>
                      </html>
                    `);
                    printWindow.document.close();
                  }}
                  className="flex items-center gap-1.5 rounded-xl border border-gold/40 bg-gold/10 px-4 py-2 text-xs font-semibold text-gold hover:bg-gold/20 transition-all"
                >
                  <Download className="h-4 w-4" /> Export PDF
                </button>
              </div>

              <div className="flex items-center gap-2">
                {selectedInvoice.status === "Draft" && (
                  <button
                    onClick={() => {
                      sendInvoice.mutate(selectedInvoice.id, { onSuccess: () => setSelectedInvoice(null) });
                    }}
                    disabled={sendInvoice.isPending}
                    className="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-xs font-bold text-black hover:shadow-[var(--shadow-gold)] disabled:opacity-50 transition-all"
                  >
                    {sendInvoice.isPending ? "Sending..." : "Send Invoice"}
                  </button>
                )}
                <button
                  onClick={() => setSelectedInvoice(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card hover:text-foreground transition-all"
                >
                  Close Window
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Record Payment Modal */}
      {paymentInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-200">
          <div className="glass-card w-full max-w-lg rounded-2xl p-6 relative bg-[#0E1017] border border-gold/40 shadow-2xl space-y-5">
            <button
              type="button"
              onClick={() => setPaymentInvoice(null)}
              className="absolute right-4 top-4 text-muted-foreground hover:text-foreground p-1 rounded-lg border border-border/40 hover:bg-card transition-colors"
            >
              <X className="h-5 w-5" />
            </button>

            <div>
              <div className="flex items-center gap-2 text-xs font-mono font-bold uppercase tracking-wider text-gold">
                <CreditCard className="h-4 w-4" /> Record / Settle Payment
              </div>
              <h2 className="font-display text-2xl font-semibold mt-1">
                Invoice #{paymentInvoice.invoiceNumber}
              </h2>
              <p className="text-xs text-muted-foreground mt-0.5">
                Client: <span className="text-foreground font-semibold">{paymentInvoice.clientName}</span> | Total Due: <span className="text-gold font-bold">£{Number(paymentInvoice.balanceDue || paymentInvoice.total).toFixed(2)}</span>
              </p>
            </div>

            <form onSubmit={handleSubmitRecordPayment} className="space-y-4">
              {/* Payment Method Selector */}
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                  Payment Method
                </label>
                <div className="grid grid-cols-3 gap-2">
                  <button
                    type="button"
                    onClick={() => setPayMethod("cash")}
                    className={`flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-bold transition-all ${
                      payMethod === "cash"
                        ? "border-gold bg-gold/15 text-gold shadow-md"
                        : "border-border bg-input/20 text-muted-foreground hover:border-gold/40"
                    }`}
                  >
                    <span className="text-lg mb-1">💵</span>
                    <span>Cash</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPayMethod("bank_transfer")}
                    className={`flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-bold transition-all ${
                      payMethod === "bank_transfer"
                        ? "border-gold bg-gold/15 text-gold shadow-md"
                        : "border-border bg-input/20 text-muted-foreground hover:border-gold/40"
                    }`}
                  >
                    <span className="text-lg mb-1">🏦</span>
                    <span>Bank Transfer</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPayMethod("stripe")}
                    className={`flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-bold transition-all ${
                      payMethod === "stripe"
                        ? "border-gold bg-gold/15 text-gold shadow-md"
                        : "border-border bg-input/20 text-muted-foreground hover:border-gold/40"
                    }`}
                  >
                    <span className="text-lg mb-1">💳</span>
                    <span>Stripe Card</span>
                  </button>
                </div>
              </div>

              {/* Amount & Date */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-muted-foreground mb-1">Amount (£)</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={payAmount}
                    onChange={(e) => setPayAmount(Number(e.target.value))}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm font-semibold outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="block text-xs text-muted-foreground mb-1">Payment Date</label>
                  <input
                    type="date"
                    required
                    value={payDate}
                    onChange={(e) => setPayDate(e.target.value)}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              {/* Reference / Notes */}
              <div>
                <label className="block text-xs text-muted-foreground mb-1">Payment Reference / Notes</label>
                <input
                  type="text"
                  value={payReference}
                  onChange={(e) => setPayReference(e.target.value)}
                  placeholder={payMethod === "cash" ? "Cash collected by staff on-site" : "Bank transaction reference / Stripe ref"}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>

              <div className="rounded-xl bg-gold/5 border border-gold/20 p-3 text-xs text-muted-foreground">
                ℹ️ Recording this payment updates the invoice status to <span className="text-emerald-400 font-semibold">Paid</span>, posts directly to the <span className="text-foreground font-semibold">General Ledger</span>, and syncs the client's deposit balance.
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/50">
                <button
                  type="button"
                  onClick={() => setPaymentInvoice(null)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isRecordingPayment}
                  className="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-2 text-xs font-bold text-white shadow-lg hover:opacity-90 disabled:opacity-50"
                >
                  {isRecordingPayment ? "Settling..." : "Confirm & Settle Payment"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Create Invoice Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
          <div className="glass-card w-full max-w-2xl rounded-2xl p-6 relative max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in duration-150">
            <button onClick={() => setIsCreateModalOpen(false)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <h2 className="font-display text-2xl font-semibold mb-1">Create New Invoice</h2>
            <p className="text-xs text-muted-foreground mb-6">Issue an official invoice with tax breakdown.</p>

            <form onSubmit={handleCreateInvoice} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Client Name</label>
                  <input
                    required
                    value={newInvoice.clientName}
                    onChange={(e) => setNewInvoice({ ...newInvoice, clientName: e.target.value })}
                    placeholder="e.g. David Miller"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Client Email</label>
                  <input
                    required
                    type="email"
                    value={newInvoice.clientEmail}
                    onChange={(e) => setNewInvoice({ ...newInvoice, clientEmail: e.target.value })}
                    placeholder="david@miller.co.uk"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Service / Job Title</label>
                  <input
                    required
                    value={newInvoice.serviceTitle}
                    onChange={(e) => setNewInvoice({ ...newInvoice, serviceTitle: e.target.value })}
                    placeholder="e.g. Office Relocation"
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">Payment Due In (Days)</label>
                  <input
                    type="number"
                    value={newInvoice.dueDays}
                    onChange={(e) => setNewInvoice({ ...newInvoice, dueDays: Number(e.target.value) })}
                    className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                  />
                </div>
              </div>

              {/* Line Items */}
              <div className="pt-2 space-y-3">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold uppercase tracking-wider text-gold">Line Items</label>
                  <button
                    type="button"
                    onClick={handleAddItem}
                    className="text-xs text-gold hover:underline flex items-center gap-1"
                  >
                    <Plus className="h-3.5 w-3.5" /> Add Line Item
                  </button>
                </div>

                {newInvoice.items.map((item, index) => (
                  <div key={index} className="grid grid-cols-12 gap-2 items-center bg-input/20 p-2.5 rounded-xl border border-border/40">
                    <div className="col-span-6">
                      <input
                        required
                        placeholder="Item Description"
                        value={item.description}
                        onChange={(e) => handleItemChange(index, "description", e.target.value)}
                        className="w-full rounded-lg border border-border bg-input/40 px-2.5 py-1.5 text-xs outline-none focus:border-gold/50"
                      />
                    </div>
                    <div className="col-span-2">
                      <input
                        type="number"
                        min="1"
                        placeholder="Qty"
                        value={item.quantity}
                        onChange={(e) => handleItemChange(index, "quantity", Number(e.target.value))}
                        className="w-full rounded-lg border border-border bg-input/40 px-2 py-1.5 text-xs outline-none focus:border-gold/50"
                      />
                    </div>
                    <div className="col-span-3">
                      <input
                        type="number"
                        placeholder="Price (£)"
                        value={item.unitPrice}
                        onChange={(e) => handleItemChange(index, "unitPrice", Number(e.target.value))}
                        className="w-full rounded-lg border border-border bg-input/40 px-2 py-1.5 text-xs outline-none focus:border-gold/50"
                      />
                    </div>
                    <div className="col-span-1 text-center">
                      {newInvoice.items.length > 1 && (
                        <button
                          type="button"
                          onClick={() => handleRemoveItem(index)}
                          className="text-destructive hover:opacity-80"
                        >
                          <X className="h-4 w-4" />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-border/50">
                <button
                  type="button"
                  onClick={() => setIsCreateModalOpen(false)}
                  className="rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-card"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
                >
                  Issue Invoice
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
