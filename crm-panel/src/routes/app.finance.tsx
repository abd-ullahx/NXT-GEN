import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import {
  Bar,
  BarChart,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
} from "recharts";
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  ArrowUpRight,
  ArrowDownRight,
  Plus,
  X,
  Filter,
  Search,
  CheckCircle2,
  DollarSign,
  Receipt,
  Sparkles,
  FileSpreadsheet,
  FileText,
  Clock,
  AlertTriangle,
  Building2,
  Calendar,
  Send,
  Printer,
  ChevronRight,
  Check,
  Eye,
  Edit,
  Trash2,
  Save,
  MapPin,
  Mail,
  User,
  ShieldCheck,
} from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import {
  fetchFinanceData,
  createTransaction,
  updateTransaction,
  deleteTransaction,
  convertQuotationToInvoice,
  recordInvoicePayment,
  sendInvoice,
  updateInvoice,
  updateQuotation,
} from "@/lib/api";

export const Route = createFileRoute("/app/finance")({
  head: () => ({ meta: [{ title: "Accounting & Financial System — Next Gen CRM" }] }),
  component: Finance,
});

type TabType = "overview" | "quotations" | "invoices" | "ledger" | "reports";

function Finance() {
  const [activeSubTab, setActiveSubTab] = useState<TabType>("overview");

  // Main financial state
  const [revenueData, setRevenueData] = useState<{ month: string; revenue: number; expenses: number }[]>([]);
  const [expenseBreakdown, setExpenseBreakdown] = useState<{ name: string; value: number }[]>([]);
  const [txs, setTxs] = useState<any[]>([]);
  const [quotations, setQuotations] = useState<any[]>([]);
  const [invoices, setInvoices] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>({
    totalIncome: 0,
    totalExpenses: 0,
    netProfit: 0,
    profitMargin: 0,
    accountsReceivable: 0,
    pendingQuotesTotal: 0,
    outputTax: 0,
    inputTax: 0,
    netTaxLiability: 0,
  });
  const [invoicesSummary, setInvoicesSummary] = useState<any>({
    totalInvoiced: 0,
    totalPaid: 0,
    accountsReceivable: 0,
    overdue: 0,
    totalCount: 0,
  });
  const [quotationsSummary, setQuotationsSummary] = useState<any>({
    totalQuotedValue: 0,
    pendingValue: 0,
    approvedValue: 0,
    winRate: 0,
    totalCount: 0,
  });

  // Filter states for Google Console style Analytics
  const [timeRange, setTimeRange] = useState<string>("12m");
  const [selectedMonth, setSelectedMonth] = useState<string>("all");
  const [showIncome, setShowIncome] = useState<boolean>(true);
  const [showExpenses, setShowExpenses] = useState<boolean>(true);
  const [isAnalyticsLoading, setIsAnalyticsLoading] = useState<boolean>(false);
  const [serviceType, setServiceType] = useState<string>("all");

  // Filter states
  const [ledgerTab, setLedgerTab] = useState<"all" | "income" | "expense">("all");
  const [invoiceStatusFilter, setInvoiceStatusFilter] = useState<string>("all");
  const [quoteStatusFilter, setQuoteStatusFilter] = useState<string>("all");
  const [searchQuery, setSearchQuery] = useState("");

  // Modals for Create / Record Payment
  const [isTxModalOpen, setIsTxModalOpen] = useState(false);
  const [isPayModalOpen, setIsPayModalOpen] = useState(false);
  const [selectedInvoiceForPay, setSelectedInvoiceForPay] = useState<any>(null);

  // Modals for VIEW DETAILS
  const [viewQuotation, setViewQuotation] = useState<any>(null);
  const [viewInvoice, setViewInvoice] = useState<any>(null);
  const [viewTx, setViewTx] = useState<any>(null);

  // Modals for EDIT
  const [editQuotation, setEditQuotation] = useState<any>(null);
  const [editInvoice, setEditInvoice] = useState<any>(null);
  const [editTx, setEditTx] = useState<any>(null);

  // Form states
  const [txFormData, setTxFormData] = useState({
    date: new Date().toISOString().split("T")[0],
    label: "",
    category: "Job Payment",
    type: "income" as "income" | "expense",
    amount: 1500,
    service_type: "domestic",
  });

  const [payFormData, setPayFormData] = useState({
    amount: 0,
    payment_method: "Bank Transfer",
    date: new Date().toISOString().split("T")[0],
    category: "Invoice Payment",
  });

  const loadFinance = (range = timeRange, month = selectedMonth, svcType = serviceType) => {
    setIsAnalyticsLoading(true);
    fetchFinanceData({ range, month, service_type: svcType })
      .then((data) => {
        if (data.summary) setSummary(data.summary);
        if (data.invoicesSummary) setInvoicesSummary(data.invoicesSummary);
        if (data.quotationsSummary) setQuotationsSummary(data.quotationsSummary);
        if (data.revenueByMonth) setRevenueData(data.revenueByMonth);
        if (data.expenseBreakdown) setExpenseBreakdown(data.expenseBreakdown);
        if (data.transactions) setTxs(data.transactions);
        if (data.quotations) setQuotations(data.quotations);
        if (data.invoices) setInvoices(data.invoices);
      })
      .catch((err) => console.warn("Failed to load finance accounting data from API:", err))
      .finally(() => setIsAnalyticsLoading(false));
  };

  useEffect(() => {
    loadFinance(timeRange, selectedMonth, serviceType);
  }, [timeRange, selectedMonth, serviceType]);

  // Handlers for Ledger Transactions
  const handleCreateTx = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!txFormData.label.trim()) return;

    try {
      await createTransaction(txFormData);
      setIsTxModalOpen(false);
      setTxFormData({
        date: new Date().toISOString().split("T")[0],
        label: "",
        category: "Job Payment",
        type: "income",
        amount: 1500,
        service_type: serviceType !== "all" ? serviceType : "domestic",
      });
      loadFinance();
    } catch (err) {
      console.error("Error creating transaction:", err);
    }
  };

  const handleUpdateTxSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editTx) return;

    try {
      await updateTransaction(editTx.id, editTx);
      setEditTx(null);
      loadFinance();
    } catch (err) {
      console.error("Error updating transaction:", err);
    }
  };

  const handleDeleteTxAction = async (id: string) => {
    if (!confirm("Are you sure you want to delete this ledger transaction?")) return;
    try {
      await deleteTransaction(id);
      loadFinance();
    } catch (err) {
      console.error("Error deleting transaction:", err);
    }
  };

  // Handlers for Invoices
  const handleRecordPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvoiceForPay) return;

    try {
      await recordInvoicePayment(selectedInvoiceForPay.id, payFormData);
      setIsPayModalOpen(false);
      setSelectedInvoiceForPay(null);
      loadFinance();
    } catch (err) {
      console.error("Error recording invoice payment:", err);
    }
  };

  const handleUpdateInvoiceSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editInvoice) return;

    try {
      await updateInvoice(editInvoice.id, {
        client_name: editInvoice.clientName,
        client_email: editInvoice.clientEmail,
        service_title: editInvoice.serviceTitle,
        status: editInvoice.status,
        issue_date: editInvoice.issueDate,
        due_date: editInvoice.dueDate,
        subtotal: editInvoice.subtotal,
        tax: editInvoice.tax,
        total: editInvoice.total,
        items: editInvoice.items,
      });
      setEditInvoice(null);
      loadFinance();
    } catch (err) {
      console.error("Error updating invoice:", err);
    }
  };

  // Handlers for Quotations
  const handleConvertQuoteToInvoice = async (quoteId: string) => {
    try {
      await convertQuotationToInvoice(quoteId);
      loadFinance();
      setActiveSubTab("invoices");
    } catch (err) {
      console.error("Error converting quote to invoice:", err);
    }
  };

  const handleUpdateQuotationSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editQuotation) return;

    try {
      await updateQuotation(editQuotation.id, {
        clientName: editQuotation.clientName,
        clientEmail: editQuotation.clientEmail,
        moveType: editQuotation.moveType,
        from: editQuotation.from,
        to: editQuotation.to,
        moveDate: editQuotation.moveDate,
        subtotal: editQuotation.subtotal,
        tax: editQuotation.tax,
        total: editQuotation.total,
        status: editQuotation.status,
        validUntil: editQuotation.validUntil,
        notes: editQuotation.notes,
        items: editQuotation.items,
      });
      setEditQuotation(null);
      loadFinance();
    } catch (err) {
      console.error("Error updating quotation:", err);
    }
  };

  const handleSendInvoice = async (invId: string) => {
    try {
      await sendInvoice(invId);
      loadFinance();
    } catch (err) {
      console.error("Error sending invoice:", err);
    }
  };

  // CSV Export functionality
  const handleExportCSV = () => {
    if (txs.length === 0) return;
    const headers = ["Transaction ID", "Date", "Label", "Category", "Type", "Amount (£)"];
    const rows = txs.map((t) => [
      t.id,
      t.date,
      `"${(t.label || "").replace(/"/g, '""')}"`,
      t.category,
      (t.type || "").toUpperCase(),
      t.amount,
    ]);

    const csvContent =
      "data:text/csv;charset=utf-8," +
      [headers.join(","), ...rows.map((e) => e.join(","))].join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `NextGen_Financial_Ledger_${new Date().toISOString().split("T")[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const COLORS = ["#10B981", "#F59E0B", "#EF4444", "#3B82F6", "#8B5CF6", "#EC4899"];

  // Filtered lists
  const filteredTxs = txs.filter((t) => {
    const matchesTab = ledgerTab === "all" || t.type === ledgerTab;
    const matchesQuery =
      (t.label || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (t.category || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (t.id || "").toLowerCase().includes(searchQuery.toLowerCase());
    return matchesTab && matchesQuery;
  });

  const filteredInvoices = invoices.filter((inv) => {
    const matchesStatus =
      invoiceStatusFilter === "all" || inv.status.toLowerCase() === invoiceStatusFilter.toLowerCase();
    const matchesQuery =
      (inv.clientName || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (inv.invoiceNumber || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (inv.serviceTitle || "").toLowerCase().includes(searchQuery.toLowerCase());
    return matchesStatus && matchesQuery;
  });

  const filteredQuotations = quotations.filter((q) => {
    const matchesStatus =
      quoteStatusFilter === "all" || q.status.toLowerCase() === quoteStatusFilter.toLowerCase();
    const matchesQuery =
      (q.clientName || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (q.quoteNumber || "").toLowerCase().includes(searchQuery.toLowerCase()) ||
      (q.from || "").toLowerCase().includes(searchQuery.toLowerCase());
    return matchesStatus && matchesQuery;
  });

  const cards = [
    {
      label: "Total Income / Revenue",
      value: `£${Number(summary.totalIncome || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`,
      delta: "Paid invoices & job deposits",
      up: true,
      icon: TrendingUp,
      tone: "text-emerald-400 border-emerald-500/30 bg-emerald-500/10",
    },
    {
      label: "Total Operating Expenses",
      value: `£${Number(summary.totalExpenses || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`,
      delta: "Fleet, fuel, wages & supplies",
      up: false,
      icon: TrendingDown,
      tone: "text-rose-400 border-rose-500/30 bg-rose-500/10",
    },
    {
      label: "Net Profit Margin",
      value: `£${Number(summary.netProfit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`,
      delta: `${summary.profitMargin || 0}% Net Margin`,
      up: summary.netProfit >= 0,
      icon: Wallet,
      tone: "text-gold border-gold/30 bg-gold/10",
    },
    {
      label: "Accounts Receivable (A/R)",
      value: `£${Number(summary.accountsReceivable || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`,
      delta: `${invoices.filter((i) => i.status !== "Paid").length} Unpaid Invoices`,
      up: false,
      icon: Receipt,
      tone: "text-amber-400 border-amber-400/30 bg-amber-400/10",
    },
  ];

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Building2 className="h-3.5 w-3.5" /> Complete Accounting System
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">Finance & Accounting Hub</h1>
          <p className="text-sm text-muted-foreground">
            Complete records of Quotations, Invoices, Payments, Profit & Loss Statements, and Tax Reporting.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handleExportCSV}
            className="flex items-center gap-2 rounded-xl border border-gold/40 bg-gold/10 px-4 py-2.5 text-xs font-semibold text-gold hover:bg-gold/20 transition-all"
          >
            <FileSpreadsheet className="h-4 w-4" /> Export Ledger (CSV)
          </button>
          <button
            onClick={() => {
              setTxFormData({ ...txFormData, type: "expense", category: "Operational Expense", label: "", amount: 0 });
              setIsTxModalOpen(true);
            }}
            className="flex items-center gap-2 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-xs font-semibold text-rose-400 hover:bg-rose-500/20 transition-all"
          >
            <Plus className="h-4 w-4" /> Add Expense
          </button>
          <button
            onClick={() => {
              setTxFormData({ ...txFormData, type: "income", category: "Job Payment", label: "", amount: 0 });
              setIsTxModalOpen(true);
            }}
            className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
          >
            <Plus className="h-4 w-4" /> Add Income
          </button>
        </div>
      </div>

      {/* Service Type Segmented Control */}
      <div className="flex items-center gap-2 bg-black/40 p-1 rounded-xl border border-border/50 w-fit">
        {[
          { id: "all", label: "All Services" },
          { id: "domestic", label: "🏠 Domestic" },
          { id: "commercial", label: "🏢 Commercial" },
          { id: "international", label: "🌍 International" },
        ].map((s) => (
          <button
            key={s.id}
            onClick={() => setServiceType(s.id)}
            className={`px-4 py-2 text-xs font-semibold rounded-lg transition-all ${
              serviceType === s.id
                ? "bg-gold text-black shadow-md"
                : "text-muted-foreground hover:text-foreground hover:bg-white/5"
            }`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Sub-Navigation Tabs */}
      <div className="flex items-center border-b border-border/60 gap-2 overflow-x-auto pb-1">
        {[
          { id: "overview", label: "Executive Dashboard", icon: Wallet },
          { id: "quotations", label: `Quotations (${quotations.length})`, icon: FileText },
          { id: "invoices", label: `Invoices & A/R (${invoices.length})`, icon: Receipt },
          { id: "ledger", label: `Cash Ledger (${txs.length})`, icon: DollarSign },
          { id: "reports", label: "Financial Reports & Tax", icon: FileSpreadsheet },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeSubTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveSubTab(tab.id as TabType)}
              className={`flex items-center gap-2 px-4 py-3 text-xs font-semibold rounded-t-xl transition-all border-b-2 whitespace-nowrap ${
                isActive
                  ? "border-gold text-gold bg-gold/10"
                  : "border-transparent text-muted-foreground hover:text-foreground hover:bg-card/40"
              }`}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* ── 1. EXECUTIVE DASHBOARD SUB-TAB ────────────────────────────────────── */}
      {activeSubTab === "overview" && (
        <div className="space-y-6 animate-in fade-in duration-200">
          <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
            {cards.map((c) => (
              <GlassCard key={c.label} hover className="p-3.5 border-gold/20 bg-[#0F1017]">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] uppercase tracking-wider text-muted-foreground">{c.label}</span>
                  <div className={`p-1.5 rounded-lg border ${c.tone}`}>
                    <c.icon className="h-3.5 w-3.5" />
                  </div>
                </div>
                <div className="mt-1 font-display text-2xl font-semibold text-foreground">{c.value}</div>
                <div
                  className={`mt-1 flex items-center gap-1 text-[11px] font-medium ${
                    c.up ? "text-emerald-400" : "text-amber-400"
                  }`}
                >
                  {c.up ? <ArrowUpRight className="h-3.5 w-3.5" /> : <ArrowDownRight className="h-3.5 w-3.5" />} {c.delta}
                </div>
              </GlassCard>
            ))}
          </div>

          {/* Google Search Console / Google Analytics style Filter Bar */}
          <GlassCard className="p-4 border-gold/20 bg-[#0F1017] flex flex-wrap items-center justify-between gap-4 relative overflow-hidden">
            <div className="flex items-center gap-2">
              <Calendar className="h-4 w-4 text-gold" />
              <span className="text-xs font-semibold text-foreground uppercase tracking-wider">Date Range Filter:</span>
              <div className="flex items-center gap-1.5 bg-black/40 p-1 rounded-xl border border-border/50">
                {[
                  { id: "30d", label: "Last 30 Days" },
                  { id: "3m", label: "3 Months" },
                  { id: "6m", label: "6 Months" },
                  { id: "12m", label: "12 Months" },
                  { id: "all", label: "All Time" },
                ].map((r) => (
                  <button
                    key={r.id}
                    onClick={() => {
                      setTimeRange(r.id);
                      setSelectedMonth("all");
                    }}
                    className={`px-3 py-1 text-xs font-medium rounded-lg transition-all ${
                      timeRange === r.id && selectedMonth === "all"
                        ? "bg-gold text-black font-semibold shadow-md"
                        : "text-muted-foreground hover:text-foreground hover:bg-white/5"
                    }`}
                  >
                    {r.label}
                  </button>
                ))}
              </div>
            </div>

            <div className="flex items-center gap-3">
              {isAnalyticsLoading && (
                <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gold/10 border border-gold/30 text-[11px] text-gold font-medium animate-pulse">
                  <Clock className="h-3 w-3 animate-spin" /> Updating Analytics...
                </div>
              )}

              <div className="flex items-center gap-2">
                <Filter className="h-3.5 w-3.5 text-muted-foreground" />
                <span className="text-xs text-muted-foreground">Select Month:</span>
                <select
                  value={selectedMonth}
                  onChange={(e) => setSelectedMonth(e.target.value)}
                  className="bg-card/80 border border-border/60 rounded-lg text-xs px-3 py-1.5 text-foreground focus:outline-none focus:border-gold"
                >
                  <option value="all">All Months in Range</option>
                  <option value="2026-09">September 2026</option>
                  <option value="2026-08">August 2026</option>
                  <option value="2026-07">July 2026</option>
                  <option value="2026-06">June 2026</option>
                  <option value="2026-05">May 2026</option>
                  <option value="2026-04">April 2026</option>
                  <option value="2026-03">March 2026</option>
                  <option value="2026-02">February 2026</option>
                  <option value="2026-01">January 2026</option>
                </select>
              </div>

              {(timeRange !== "12m" || selectedMonth !== "all") && (
                <button
                  onClick={() => {
                    setTimeRange("12m");
                    setSelectedMonth("all");
                  }}
                  className="text-xs text-gold hover:underline flex items-center gap-1"
                >
                  <X className="h-3 w-3" /> Reset Filter
                </button>
              )}
            </div>
          </GlassCard>

          <div className="grid gap-6 lg:grid-cols-3">
            <GlassCard className="p-6 lg:col-span-2 border-gold/20 bg-[#0F1017]">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                <div>
                  <h2 className="font-display text-xl font-semibold text-foreground tracking-tight">Monthly P&L Performance</h2>
                  <p className="text-xs text-muted-foreground mt-0.5">Aggregated real-time income streams from invoices & won jobs vs costs.</p>
                </div>
                
                {/* Google Analytics style Toggle Legend Buttons */}
                <div className="flex items-center gap-2 text-xs font-medium shrink-0">
                  <button
                    onClick={() => setShowIncome(!showIncome)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border transition-all ${
                      showIncome
                        ? "border-gold/50 bg-gold/10 text-gold font-semibold"
                        : "border-border/40 text-muted-foreground/50 opacity-60 hover:opacity-100"
                    }`}
                  >
                    <span className={`h-2.5 w-2.5 rounded-full ${showIncome ? "bg-gold" : "bg-muted-foreground"}`} />
                    Income
                  </button>

                  <button
                    onClick={() => setShowExpenses(!showExpenses)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border transition-all ${
                      showExpenses
                        ? "border-rose-500/50 bg-rose-500/10 text-rose-400 font-semibold"
                        : "border-border/40 text-muted-foreground/50 opacity-60 hover:opacity-100"
                    }`}
                  >
                    <span className={`h-2.5 w-2.5 rounded-full ${showExpenses ? "bg-rose-400" : "bg-muted-foreground"}`} />
                    Expenses
                  </button>
                </div>
              </div>

              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={revenueData} margin={{ left: -10, right: 10, top: 10, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(255, 255, 255, 0.06)" vertical={false} />
                    <XAxis dataKey="month" stroke="#9CA3AF" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis
                      stroke="#9CA3AF"
                      fontSize={11}
                      tickLine={false}
                      axisLine={false}
                      tickFormatter={(v) => (v >= 1000 ? `£${(v / 1000).toFixed(v % 1000 === 0 ? 0 : 1)}k` : `£${v}`)}
                    />
                    <Tooltip
                      cursor={{ fill: "rgba(212, 175, 55, 0.06)" }}
                      contentStyle={{
                        backgroundColor: "#141720",
                        borderColor: "rgba(201, 168, 76, 0.4)",
                        borderRadius: "12px",
                        boxShadow: "0 10px 25px rgba(0,0,0,0.6)",
                        padding: "10px 14px",
                      }}
                      itemStyle={{ color: "#FFFFFF", fontSize: "12px", fontWeight: "bold" }}
                      labelStyle={{ color: "#C9A84C", fontSize: "11px", fontWeight: "bold", marginBottom: "4px" }}
                      formatter={(v: number) => `£${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}
                    />
                    {showIncome && <Bar dataKey="revenue" fill="#C9A84C" radius={[6, 6, 0, 0]} maxBarSize={24} name="Income" />}
                    {showExpenses && <Bar dataKey="expenses" fill="#F43F5E" radius={[6, 6, 0, 0]} maxBarSize={24} name="Expenses" />}
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </GlassCard>

            <GlassCard className="p-6 border-gold/20 bg-[#0F1017]">
              <h2 className="mb-1 font-display text-xl font-semibold text-foreground tracking-tight">Expense Distribution</h2>
              <p className="text-xs text-muted-foreground mb-4">Operational costs split by category.</p>
              
              {expenseBreakdown.length === 0 ? (
                <div className="h-56 flex flex-col items-center justify-center text-center p-4 border border-dashed border-border/60 rounded-2xl bg-card/20">
                  <p className="text-xs text-muted-foreground">No expense transactions recorded yet.</p>
                  <p className="text-[11px] text-muted-foreground/70 mt-1">Use "Record Income / Expense" above to add operational expenses.</p>
                </div>
              ) : (
                <>
                  <div className="h-44">
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie data={expenseBreakdown} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={44} outerRadius={68} paddingAngle={3}>
                          {expenseBreakdown.map((e, idx) => (
                            <Cell key={e.name} fill={COLORS[idx % COLORS.length]} stroke="transparent" />
                          ))}
                        </Pie>
                        <Tooltip
                          contentStyle={{
                            backgroundColor: "#141720",
                            borderColor: "rgba(201, 168, 76, 0.4)",
                            borderRadius: "12px",
                            boxShadow: "0 10px 25px rgba(0,0,0,0.6)",
                            padding: "10px 14px",
                          }}
                          itemStyle={{ color: "#FFFFFF", fontSize: "12px", fontWeight: "bold" }}
                          labelStyle={{ color: "#C9A84C", fontSize: "11px", fontWeight: "bold", marginBottom: "4px" }}
                          formatter={(v: number) => `£${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}
                        />
                      </PieChart>
                    </ResponsiveContainer>
                  </div>
                  <div className="mt-2 space-y-1.5">
                    {expenseBreakdown.map((e, idx) => (
                      <div key={e.name} className="flex items-center justify-between text-xs">
                        <span className="flex items-center gap-2 text-muted-foreground">
                          <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ background: COLORS[idx % COLORS.length] }} />
                          <span className="truncate max-w-[140px]">{e.name}</span>
                        </span>
                        <span className="font-semibold font-mono text-foreground">£{Number(e.value).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </GlassCard>
          </div>

          <GlassCard className="p-6 border-gold/20 bg-[#0F1017]">
            <h2 className="font-display text-xl font-semibold text-foreground mb-4">Quick Financial Health Summary</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-4 rounded-2xl bg-card/40 border border-border/60">
                <div className="text-xs text-muted-foreground uppercase font-semibold">Quotations Win Rate</div>
                <div className="text-2xl font-bold font-mono text-gold mt-1">{quotationsSummary.winRate}%</div>
                <p className="text-[11px] text-muted-foreground mt-1">
                  £{Number(quotationsSummary.approvedValue || 0).toLocaleString()} approved out of £{Number(quotationsSummary.totalQuotedValue || 0).toLocaleString()} quoted
                </p>
              </div>

              <div className="p-4 rounded-2xl bg-card/40 border border-border/60">
                <div className="text-xs text-muted-foreground uppercase font-semibold">Invoices Collection Rate</div>
                <div className="text-2xl font-bold font-mono text-emerald-400 mt-1">
                  {invoicesSummary.totalInvoiced > 0
                    ? round((invoicesSummary.totalPaid / invoicesSummary.totalInvoiced) * 100)
                    : 100}%
                </div>
                <p className="text-[11px] text-muted-foreground mt-1">
                  £{Number(invoicesSummary.totalPaid || 0).toLocaleString()} collected out of £{Number(invoicesSummary.totalInvoiced || 0).toLocaleString()} invoiced
                </p>
              </div>

              <div className="p-4 rounded-2xl bg-card/40 border border-border/60">
                <div className="text-xs text-muted-foreground uppercase font-semibold">Tax (VAT) Liability</div>
                <div className="text-2xl font-bold font-mono text-amber-400 mt-1">
                  £{Number(summary.netTaxLiability || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </div>
                <p className="text-[11px] text-muted-foreground mt-1">
                  Output VAT £{Number(summary.outputTax || 0).toLocaleString()} − Input VAT £{Number(summary.inputTax || 0).toLocaleString()}
                </p>
              </div>
            </div>
          </GlassCard>
        </div>
      )}

      {/* ── 2. QUOTATIONS REGISTER SUB-TAB ────────────────────────────────────── */}
      {activeSubTab === "quotations" && (
        <div className="space-y-6 animate-in fade-in duration-200">
          <GlassCard className="overflow-hidden border-gold/20 bg-[#0F1017]">
            <div className="p-5 border-b border-border/60 flex flex-wrap items-center justify-between gap-4">
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Quotations Register</h2>
                <p className="text-xs text-muted-foreground">Full detail inspect, edit, and invoice conversion for all quotations.</p>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center bg-card/60 p-1 rounded-xl border border-border/60">
                  {["all", "draft", "sent", "approved", "declined"].map((st) => (
                    <button
                      key={st}
                      onClick={() => setQuoteStatusFilter(st)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition-all ${
                        quoteStatusFilter === st
                          ? "bg-gold text-black shadow-md"
                          : "text-muted-foreground hover:text-foreground"
                      }`}
                    >
                      {st}
                    </button>
                  ))}
                </div>

                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search quotations…"
                    className="rounded-xl border border-border bg-input/40 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
                  />
                </div>
              </div>
            </div>

            <div className="divide-y divide-border/40">
              {filteredQuotations.length === 0 ? (
                <div className="p-8 text-center text-xs text-muted-foreground">No quotations found matching criteria.</div>
              ) : (
                filteredQuotations.map((q) => (
                  <div key={q.id} className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-card/30 transition-all">
                    <div className="flex items-center gap-3.5 min-w-0 flex-1">
                      <div className="flex h-10 w-10 items-center justify-center rounded-2xl shrink-0 bg-gold/15 border border-gold/30 text-gold font-mono font-bold text-xs">
                        QT
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-sm text-foreground truncate">{q.clientName}</span>
                          <span className="font-mono text-xs text-gold shrink-0">#{q.quoteNumber || q.id}</span>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mt-0.5 min-w-0">
                          <span className="truncate max-w-xs sm:max-w-md lg:max-w-xl">📍 {q.from || "TBC"} ➔ {q.to || "TBC"}</span>
                          <span className="shrink-0">· Date: {q.moveDate || "TBC"}</span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                      <div className="text-right pr-2">
                        <div className="text-base font-bold font-mono text-gold">
                          £{Number(q.total).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </div>
                        <span
                          className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                            q.status === "approved"
                              ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"
                              : q.status === "sent"
                              ? "bg-amber-500/20 text-amber-400 border border-amber-500/40"
                              : q.status === "declined"
                              ? "bg-rose-500/20 text-rose-400 border border-rose-500/40"
                              : "bg-slate-500/20 text-slate-400 border border-slate-500/40"
                          }`}
                        >
                          {q.status}
                        </span>
                      </div>

                      {/* Action buttons: View Detail, Edit, Convert */}
                      <button
                        onClick={() => setViewQuotation(q)}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-foreground hover:border-gold/40 transition-all"
                        title="View Full Details"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => setEditQuotation({ ...q })}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                        title="Edit Quotation"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => handleConvertQuoteToInvoice(q.id)}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gold/40 bg-gold/10 text-gold hover:bg-gold/20 text-xs font-semibold transition-all"
                      >
                        <Receipt className="h-3.5 w-3.5" /> Convert to Invoice
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </GlassCard>
        </div>
      )}

      {/* ── 3. INVOICES & ACCOUNTS RECEIVABLE SUB-TAB ─────────────────────────── */}
      {activeSubTab === "invoices" && (
        <div className="space-y-6 animate-in fade-in duration-200">
          <GlassCard className="overflow-hidden border-gold/20 bg-[#0F1017]">
            <div className="p-5 border-b border-border/60 flex flex-wrap items-center justify-between gap-4">
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Invoices & Accounts Receivable (A/R)</h2>
                <p className="text-xs text-muted-foreground">Full detail inspect, edit billing info, send, and log payments.</p>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center bg-card/60 p-1 rounded-xl border border-border/60">
                  {["all", "unpaid", "paid", "overdue", "draft"].map((st) => (
                    <button
                      key={st}
                      onClick={() => setInvoiceStatusFilter(st)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition-all ${
                        invoiceStatusFilter === st
                          ? "bg-gold text-black shadow-md"
                          : "text-muted-foreground hover:text-foreground"
                      }`}
                    >
                      {st}
                    </button>
                  ))}
                </div>

                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search invoices…"
                    className="rounded-xl border border-border bg-input/40 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
                  />
                </div>
              </div>
            </div>

            <div className="divide-y divide-border/40">
              {filteredInvoices.length === 0 ? (
                <div className="p-8 text-center text-xs text-muted-foreground">No invoices found matching criteria.</div>
              ) : (
                filteredInvoices.map((inv) => (
                  <div key={inv.id} className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-card/30 transition-all">
                    <div className="flex items-center gap-3.5 min-w-0 flex-1">
                      <div className="flex h-10 w-10 items-center justify-center rounded-2xl shrink-0 bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 font-mono font-bold text-xs">
                        INV
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-sm text-foreground truncate">{inv.clientName}</span>
                          <span className="font-mono text-xs text-gold shrink-0">#{inv.invoiceNumber || inv.id}</span>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mt-0.5 min-w-0">
                          <span className="truncate">{inv.serviceTitle}</span>
                          <span className="shrink-0">· Due: {inv.dueDate || "N/A"}</span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                      <div className="text-right pr-2">
                        <div className="text-base font-bold font-mono text-foreground">
                          £{Number(inv.total).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </div>
                        <span
                          className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                            inv.status === "Paid"
                              ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"
                              : inv.status === "Overdue"
                              ? "bg-rose-500/20 text-rose-400 border border-rose-500/40"
                              : "bg-amber-500/20 text-amber-400 border border-amber-500/40"
                          }`}
                        >
                          {inv.status}
                        </span>
                      </div>

                      {/* Action buttons: View Detail, Edit, Send, Record Payment */}
                      <button
                        onClick={() => setViewInvoice(inv)}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-foreground hover:border-gold/40 transition-all"
                        title="View Full Details"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => setEditInvoice({ ...inv })}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                        title="Edit Invoice"
                      >
                        <Edit className="h-4 w-4" />
                      </button>

                      {inv.status !== "Paid" && (
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => handleSendInvoice(inv.id)}
                            className="flex items-center gap-1 px-3 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                          >
                            <Send className="h-3.5 w-3.5" /> Send
                          </button>
                          <button
                            onClick={() => {
                              setSelectedInvoiceForPay(inv);
                              setPayFormData({
                                amount: inv.total,
                                payment_method: "Bank Transfer",
                                date: new Date().toISOString().split("T")[0],
                                category: "Invoice Payment",
                              });
                              setIsPayModalOpen(true);
                            }}
                            className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-black text-xs font-semibold hover:shadow-lg transition-all"
                          >
                            <CheckCircle2 className="h-3.5 w-3.5" /> Record Payment
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </GlassCard>
        </div>
      )}

      {/* ── 4. PAYMENTS & CASH LEDGER SUB-TAB ─────────────────────────────────── */}
      {activeSubTab === "ledger" && (
        <div className="space-y-6 animate-in fade-in duration-200">
          <GlassCard className="overflow-hidden border-gold/20 bg-[#0F1017]">
            <div className="p-5 border-b border-border/60 flex flex-wrap items-center justify-between gap-4">
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Centralized Financial Cash Ledger</h2>
                <p className="text-xs text-muted-foreground">Inspect full details, edit transactions, and manage double-entry records.</p>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center bg-card/60 p-1 rounded-xl border border-border/60">
                  <button
                    onClick={() => setLedgerTab("all")}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                      ledgerTab === "all" ? "bg-gold text-black shadow-md" : "text-muted-foreground hover:text-foreground"
                    }`}
                  >
                    All ({txs.length})
                  </button>
                  <button
                    onClick={() => setLedgerTab("income")}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                      ledgerTab === "income"
                        ? "bg-emerald-500 text-black shadow-md"
                        : "text-muted-foreground hover:text-foreground"
                    }`}
                  >
                    Income (+)
                  </button>
                  <button
                    onClick={() => setLedgerTab("expense")}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                      ledgerTab === "expense"
                        ? "bg-rose-500 text-black shadow-md"
                        : "text-muted-foreground hover:text-foreground"
                    }`}
                  >
                    Expenses (−)
                  </button>
                </div>

                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search ledger…"
                    className="rounded-xl border border-border bg-input/40 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-gold/50"
                  />
                </div>
              </div>
            </div>

            <div className="divide-y divide-border/40">
              {filteredTxs.length === 0 ? (
                <div className="p-8 text-center text-xs text-muted-foreground">No transactions found matching criteria.</div>
              ) : (
                filteredTxs.map((t) => (
                  <div key={t.id} className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-card/30 transition-all">
                    <div className="flex items-center gap-3.5 min-w-0">
                      <div
                        className={`flex h-10 w-10 items-center justify-center rounded-2xl shrink-0 ${
                          t.type === "income"
                            ? "bg-emerald-500/15 border border-emerald-500/30 text-emerald-400"
                            : "bg-rose-500/15 border border-rose-500/30 text-rose-400"
                        }`}
                      >
                        {t.type === "income" ? <ArrowDownRight className="h-5 w-5" /> : <ArrowUpRight className="h-5 w-5" />}
                      </div>

                      <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-foreground">{t.label}</div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                          <span className="px-2 py-0.2 rounded-full text-[10px] font-mono bg-gold/10 text-gold border border-gold/20">
                            {t.category}
                          </span>
                          <span>Ref: <strong className="font-mono text-foreground">{t.id}</strong></span>
                          <span>· {t.date}</span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-4 shrink-0">
                      <div className="text-right">
                        <div className={`text-base font-bold font-mono ${t.type === "income" ? "text-emerald-400" : "text-rose-400"}`}>
                          {t.type === "income" ? "+" : "−"}£{Number(t.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </div>
                        <span className="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">
                          {t.type === "income" ? "Verified Received" : "Operational Cost"}
                        </span>
                      </div>

                      {/* Action buttons: View, Edit, Delete */}
                      <button
                        onClick={() => setViewTx(t)}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-foreground hover:border-gold/40 transition-all"
                        title="View Full Details"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => setEditTx({ ...t })}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                        title="Edit Transaction"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => handleDeleteTxAction(t.id)}
                        className="p-2 rounded-xl border border-border bg-card/60 text-muted-foreground hover:text-rose-400 hover:border-rose-500/40 transition-all"
                        title="Delete Transaction"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </GlassCard>
        </div>
      )}

      {/* ── 5. FINANCIAL STATEMENTS & TAX REPORTS SUB-TAB ─────────────────────── */}
      {activeSubTab === "reports" && (
        <div className="space-y-6 animate-in fade-in duration-200">
          <GlassCard className="p-8 border-gold/20 bg-[#0F1017] space-y-6">
            <div className="flex flex-wrap items-center justify-between border-b border-border/60 pb-4 gap-4">
              <div>
                <div className="flex items-center gap-2 text-gold text-xs font-semibold uppercase tracking-wider">
                  <Sparkles className="h-4 w-4" /> Next Gen Relocation Ltd — Financial Accounting
                </div>
                <h2 className="text-2xl font-bold font-display text-foreground mt-1">Profit & Loss Statement (P&L)</h2>
                <p className="text-xs text-muted-foreground">Formal Income & Expense Statement for tax and management reporting.</p>
              </div>

              <button
                onClick={() => window.print()}
                className="flex items-center gap-2 px-4 py-2 rounded-xl border border-gold/40 bg-gold/10 text-gold hover:bg-gold/20 text-xs font-semibold transition-all"
              >
                <Printer className="h-4 w-4" /> Print / Export PDF
              </button>
            </div>

            <div className="space-y-6 font-mono text-xs">
              <div className="space-y-2">
                <div className="flex justify-between font-bold text-sm text-gold border-b border-gold/20 pb-1">
                  <span>1. OPERATING REVENUE</span>
                  <span>AMOUNT (£)</span>
                </div>
                <div className="flex justify-between text-muted-foreground pl-4">
                  <span>Relocation Services & Job Payments</span>
                  <span>£{Number(summary.totalIncome || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div className="flex justify-between font-bold text-foreground pl-4 pt-1 border-t border-border/30">
                  <span>TOTAL OPERATING REVENUE</span>
                  <span className="text-emerald-400">£{Number(summary.totalIncome || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex justify-between font-bold text-sm text-gold border-b border-gold/20 pb-1">
                  <span>2. OPERATING EXPENSES</span>
                  <span>AMOUNT (£)</span>
                </div>
                {expenseBreakdown.map((exp) => (
                  <div key={exp.name} className="flex justify-between text-muted-foreground pl-4">
                    <span>{exp.name}</span>
                    <span>£{Number(exp.value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                  </div>
                ))}
                <div className="flex justify-between font-bold text-foreground pl-4 pt-1 border-t border-border/30">
                  <span>TOTAL OPERATING EXPENSES</span>
                  <span className="text-rose-400">£{Number(summary.totalExpenses || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
              </div>

              <div className="p-4 rounded-2xl bg-gold/10 border border-gold/30 flex justify-between items-center text-sm font-bold">
                <span className="text-gold uppercase tracking-wider">NET OPERATING PROFIT (BEFORE TAX)</span>
                <span className="text-lg text-gold">£{Number(summary.netProfit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>

              <div className="space-y-2 pt-4 border-t border-border/60">
                <div className="flex justify-between font-bold text-sm text-amber-400 border-b border-amber-400/20 pb-1">
                  <span>3. TAXATION & VAT RETURN SUMMARY (20% UK VAT)</span>
                  <span>AMOUNT (£)</span>
                </div>
                <div className="flex justify-between text-muted-foreground pl-4">
                  <span>Output VAT (Collected on Invoices & Sales)</span>
                  <span>£{Number(summary.outputTax || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div className="flex justify-between text-muted-foreground pl-4">
                  <span>Input VAT (Reclaimable on Operational Expenses)</span>
                  <span>£{Number(summary.inputTax || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div className="flex justify-between font-bold text-foreground pl-4 pt-1 border-t border-border/30">
                  <span>NET ESTIMATED TAX / VAT PAYABLE</span>
                  <span className="text-amber-400">£{Number(summary.netTaxLiability || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
              </div>
            </div>
          </GlassCard>
        </div>
      )}

      {/* ── MODALS SECTION ─────────────────────────────────────────────────── */}

      {/* 1. VIEW QUOTATION DETAILS MODAL */}
      {viewQuotation && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-2xl rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5 max-h-[90vh] overflow-y-auto">
            <button onClick={() => setViewQuotation(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <FileText className="h-5 w-5" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="font-display text-xl font-semibold text-foreground">Quotation Details</h2>
                  <span className="font-mono text-xs text-gold">#{viewQuotation.quoteNumber || viewQuotation.id}</span>
                </div>
                <p className="text-xs text-muted-foreground">Full post-survey quotation specification & pricing breakdown</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 text-xs">
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><User className="h-3.5 w-3.5 text-gold" /> Client Name</div>
                <div className="font-semibold text-foreground">{viewQuotation.clientName}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-gold" /> Email Address</div>
                <div className="font-semibold text-foreground">{viewQuotation.clientEmail || "N/A"}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5 text-gold" /> From Location</div>
                <div className="font-semibold text-foreground">{viewQuotation.from || "TBC"}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5 text-gold" /> To Location</div>
                <div className="font-semibold text-foreground">{viewQuotation.to || "TBC"}</div>
              </div>
            </div>

            {/* Pricing Details */}
            <div className="bg-card/40 p-4 rounded-2xl border border-border/60 space-y-2 text-xs font-mono">
              <div className="flex justify-between text-muted-foreground">
                <span>Subtotal Amount:</span>
                <span>£{Number(viewQuotation.subtotal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
              <div className="flex justify-between text-muted-foreground">
                <span>Tax (20% VAT):</span>
                <span>£{Number(viewQuotation.tax || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
              <div className="flex justify-between font-bold text-sm text-gold pt-2 border-t border-border/40">
                <span>Total Quote Amount:</span>
                <span>£{Number(viewQuotation.total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
              <button
                onClick={() => setViewQuotation(null)}
                className="px-5 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 2. EDIT QUOTATION MODAL */}
      {editQuotation && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-xl rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5 max-h-[90vh] overflow-y-auto">
            <button onClick={() => setEditQuotation(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <Edit className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Edit Quotation #{editQuotation.quoteNumber}</h2>
                <p className="text-xs text-muted-foreground">Update client details, move date, status, and prices</p>
              </div>
            </div>

            <form onSubmit={handleUpdateQuotationSubmit} className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Client Name</label>
                  <input
                    required
                    value={editQuotation.clientName}
                    onChange={(e) => setEditQuotation({ ...editQuotation, clientName: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Client Email</label>
                  <input
                    required
                    type="email"
                    value={editQuotation.clientEmail}
                    onChange={(e) => setEditQuotation({ ...editQuotation, clientEmail: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">From Location</label>
                  <input
                    value={editQuotation.from || ""}
                    onChange={(e) => setEditQuotation({ ...editQuotation, from: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">To Location</label>
                  <input
                    value={editQuotation.to || ""}
                    onChange={(e) => setEditQuotation({ ...editQuotation, to: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Quotation Status</label>
                  <select
                    value={editQuotation.status}
                    onChange={(e) => setEditQuotation({ ...editQuotation, status: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  >
                    <option value="draft" className="bg-card text-foreground">Draft</option>
                    <option value="sent" className="bg-card text-foreground">Sent</option>
                    <option value="approved" className="bg-card text-foreground">Approved</option>
                    <option value="declined" className="bg-card text-foreground">Declined</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Total Amount (£)</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={editQuotation.total}
                    onChange={(e) => {
                      const tot = Number(e.target.value);
                      const sub = round(tot / 1.2 * 100) / 100;
                      const tx = round((tot - sub) * 100) / 100;
                      setEditQuotation({ ...editQuotation, total: tot, subtotal: sub, tax: tx });
                    }}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground font-mono"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setEditQuotation(null)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
                >
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 3. VIEW INVOICE DETAILS MODAL */}
      {viewInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-2xl rounded-3xl p-6 relative border border-emerald-500/40 shadow-2xl bg-[#0F1017] space-y-5 max-h-[90vh] overflow-y-auto">
            <button onClick={() => setViewInvoice(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                <Receipt className="h-5 w-5" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="font-display text-xl font-semibold text-foreground">Tax Invoice Details</h2>
                  <span className="font-mono text-xs text-gold">#{viewInvoice.invoiceNumber || viewInvoice.id}</span>
                </div>
                <p className="text-xs text-muted-foreground">Official billing document details and tax breakdown</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 text-xs">
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><User className="h-3.5 w-3.5 text-emerald-400" /> Client Name</div>
                <div className="font-semibold text-foreground">{viewInvoice.clientName}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-emerald-400" /> Client Email</div>
                <div className="font-semibold text-foreground">{viewInvoice.clientEmail || "N/A"}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><Calendar className="h-3.5 w-3.5 text-emerald-400" /> Issue Date</div>
                <div className="font-semibold text-foreground">{viewInvoice.issueDate || "N/A"}</div>
              </div>
              <div className="space-y-1 bg-card/40 p-3 rounded-xl border border-border/50">
                <div className="text-muted-foreground flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-emerald-400" /> Payment Due Date</div>
                <div className="font-semibold text-foreground">{viewInvoice.dueDate || "N/A"}</div>
              </div>
            </div>

            {/* Pricing Details */}
            <div className="bg-card/40 p-4 rounded-2xl border border-border/60 space-y-2 text-xs font-mono">
              <div className="flex justify-between text-muted-foreground">
                <span>Subtotal Amount:</span>
                <span>£{Number(viewInvoice.subtotal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
              <div className="flex justify-between text-muted-foreground">
                <span>Tax (20% VAT):</span>
                <span>£{Number(viewInvoice.tax || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
              <div className="flex justify-between font-bold text-sm text-emerald-400 pt-2 border-t border-border/40">
                <span>Total Invoice Amount:</span>
                <span>£{Number(viewInvoice.total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
              <button
                onClick={() => setViewInvoice(null)}
                className="px-5 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 4. EDIT INVOICE MODAL */}
      {editInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-xl rounded-3xl p-6 relative border border-emerald-500/40 shadow-2xl bg-[#0F1017] space-y-5 max-h-[90vh] overflow-y-auto">
            <button onClick={() => setEditInvoice(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                <Edit className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Edit Invoice #{editInvoice.invoiceNumber}</h2>
                <p className="text-xs text-muted-foreground">Update invoice title, status, amounts, and dates</p>
              </div>
            </div>

            <form onSubmit={handleUpdateInvoiceSubmit} className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Client Name</label>
                  <input
                    required
                    value={editInvoice.clientName}
                    onChange={(e) => setEditInvoice({ ...editInvoice, clientName: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Client Email</label>
                  <input
                    required
                    type="email"
                    value={editInvoice.clientEmail}
                    onChange={(e) => setEditInvoice({ ...editInvoice, clientEmail: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Invoice Status</label>
                  <select
                    value={editInvoice.status}
                    onChange={(e) => setEditInvoice({ ...editInvoice, status: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  >
                    <option value="Paid" className="bg-card text-foreground">Paid</option>
                    <option value="Unpaid" className="bg-card text-foreground">Unpaid</option>
                    <option value="Overdue" className="bg-card text-foreground">Overdue</option>
                    <option value="Draft" className="bg-card text-foreground">Draft</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Total Amount (£)</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={editInvoice.total}
                    onChange={(e) => {
                      const tot = Number(e.target.value);
                      const sub = round(tot / 1.2 * 100) / 100;
                      const tx = round((tot - sub) * 100) / 100;
                      setEditInvoice({ ...editInvoice, total: tot, subtotal: sub, tax: tx });
                    }}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Issue Date</label>
                  <input
                    type="date"
                    value={editInvoice.issueDate || ""}
                    onChange={(e) => setEditInvoice({ ...editInvoice, issueDate: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Due Date</label>
                  <input
                    type="date"
                    value={editInvoice.dueDate || ""}
                    onChange={(e) => setEditInvoice({ ...editInvoice, dueDate: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setEditInvoice(null)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-xs font-semibold text-black hover:shadow-lg transition-all"
                >
                  Save Invoice Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 5. VIEW TRANSACTION DETAILS MODAL */}
      {viewTx && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-md rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5">
            <button onClick={() => setViewTx(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <DollarSign className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Transaction Details</h2>
                <p className="text-xs text-muted-foreground font-mono">Ref: {viewTx.id}</p>
              </div>
            </div>

            <div className="space-y-3 text-xs font-mono">
              <div className="flex justify-between py-1 border-b border-border/30">
                <span className="text-muted-foreground font-sans">Label:</span>
                <span className="font-bold text-foreground font-sans">{viewTx.label}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-border/30">
                <span className="text-muted-foreground font-sans">Category:</span>
                <span className="text-gold">{viewTx.category}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-border/30">
                <span className="text-muted-foreground font-sans">Type:</span>
                <span className={`uppercase font-bold ${viewTx.type === "income" ? "text-emerald-400" : "text-rose-400"}`}>
                  {viewTx.type}
                </span>
              </div>
              <div className="flex justify-between py-1 border-b border-border/30">
                <span className="text-muted-foreground font-sans">Date:</span>
                <span className="text-foreground">{viewTx.date}</span>
              </div>
              <div className="flex justify-between py-2 text-sm font-bold border-t border-border/60">
                <span className="text-muted-foreground font-sans">Amount:</span>
                <span className={viewTx.type === "income" ? "text-emerald-400" : "text-rose-400"}>
                  £{Number(viewTx.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </span>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
              <button
                onClick={() => setViewTx(null)}
                className="px-5 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 6. EDIT TRANSACTION MODAL */}
      {editTx && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-lg rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5">
            <button onClick={() => setEditTx(null)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <Edit className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Edit Ledger Transaction</h2>
                <p className="text-xs text-muted-foreground font-mono">Ref: {editTx.id}</p>
              </div>
            </div>

            <form onSubmit={handleUpdateTxSubmit} className="space-y-4 text-xs">
              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Transaction Title / Label</label>
                <input
                  required
                  value={editTx.label}
                  onChange={(e) => setEditTx({ ...editTx, label: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Transaction Type</label>
                  <select
                    value={editTx.type}
                    onChange={(e) => setEditTx({ ...editTx, type: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  >
                    <option value="income" className="bg-card text-foreground">Income (+)</option>
                    <option value="expense" className="bg-card text-foreground">Expense (−)</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Category</label>
                  <input
                    value={editTx.category}
                    onChange={(e) => setEditTx({ ...editTx, category: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Amount (£)</label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    value={editTx.amount}
                    onChange={(e) => setEditTx({ ...editTx, amount: Number(e.target.value) })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Transaction Date</label>
                  <input
                    type="date"
                    value={editTx.date || ""}
                    onChange={(e) => setEditTx({ ...editTx, date: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setEditTx(null)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
                >
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 7. RECORD NEW TRANSACTION MODAL */}
      {isTxModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-lg rounded-3xl p-6 relative border border-gold/40 shadow-2xl bg-[#0F1017] space-y-5">
            <button onClick={() => setIsTxModalOpen(false)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/20 text-gold border border-gold/30">
                <DollarSign className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Record Financial Transaction</h2>
                <p className="text-xs text-muted-foreground">Directly log income or expense into MySQL general ledger</p>
              </div>
            </div>

            <form onSubmit={handleCreateTx} className="space-y-4 text-xs">
              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Transaction Title / Label *</label>
                <input
                  required
                  value={txFormData.label}
                  onChange={(e) => setTxFormData({ ...txFormData, label: e.target.value })}
                  placeholder="e.g. Relocation Job Deposit — Smith (L-1950)"
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Transaction Type *</label>
                  <select
                    value={txFormData.type}
                    onChange={(e) => setTxFormData({ ...txFormData, type: e.target.value as "income" | "expense" })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  >
                    <option value="income" className="bg-card text-foreground">Income (+)</option>
                    <option value="expense" className="bg-card text-foreground">Expense (−)</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Category</label>
                  <input
                    value={txFormData.category}
                    onChange={(e) => setTxFormData({ ...txFormData, category: e.target.value })}
                    placeholder="e.g. Job Payment, Fleet Fuel"
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Amount (£) *</label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    value={txFormData.amount}
                    onChange={(e) => setTxFormData({ ...txFormData, amount: Number(e.target.value) })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Transaction Date</label>
                  <input
                    type="date"
                    value={txFormData.date}
                    onChange={(e) => setTxFormData({ ...txFormData, date: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Service Division</label>
                <select
                  value={txFormData.service_type}
                  onChange={(e) => setTxFormData({ ...txFormData, service_type: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                >
                  <option value="domestic" className="bg-card text-foreground">🏠 Domestic</option>
                  <option value="commercial" className="bg-card text-foreground">🏢 Commercial</option>
                  <option value="international" className="bg-card text-foreground">🌍 International</option>
                  <option value="general" className="bg-card text-foreground">⚙️ General / Overhead</option>
                </select>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setIsTxModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] transition-all"
                >
                  Save Transaction
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 8. RECORD INVOICE PAYMENT MODAL */}
      {isPayModalOpen && selectedInvoiceForPay && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
          <div className="glass-card w-full max-w-lg rounded-3xl p-6 relative border border-emerald-500/40 shadow-2xl bg-[#0F1017] space-y-5">
            <button onClick={() => setIsPayModalOpen(false)} className="absolute right-4 top-4 text-muted-foreground hover:text-foreground">
              <X className="h-5 w-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-border/60 pb-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                <CheckCircle2 className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold text-foreground">Record Invoice Payment</h2>
                <p className="text-xs text-muted-foreground">
                  Invoice #{selectedInvoiceForPay.invoiceNumber} — {selectedInvoiceForPay.clientName}
                </p>
              </div>
            </div>

            <form onSubmit={handleRecordPayment} className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Payment Amount (£)</label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    value={payFormData.amount}
                    onChange={(e) => setPayFormData({ ...payFormData, amount: Number(e.target.value) })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-muted-foreground font-medium">Payment Method</label>
                  <select
                    value={payFormData.payment_method}
                    onChange={(e) => setPayFormData({ ...payFormData, payment_method: e.target.value })}
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                  >
                    <option value="Bank Transfer" className="bg-card text-foreground">Bank Transfer (BACS)</option>
                    <option value="Stripe / Credit Card" className="bg-card text-foreground">Stripe / Credit Card</option>
                    <option value="Cash" className="bg-card text-foreground">Cash</option>
                    <option value="Cheque" className="bg-card text-foreground">Cheque</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-muted-foreground font-medium">Payment Date</label>
                <input
                  type="date"
                  value={payFormData.date}
                  onChange={(e) => setPayFormData({ ...payFormData, date: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 py-2.5 px-3 text-xs outline-none focus:border-emerald-500/50 text-foreground"
                />
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-border/60">
                <button
                  type="button"
                  onClick={() => setIsPayModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-border bg-card/60 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-xs font-semibold text-black hover:shadow-lg transition-all"
                >
                  Confirm Payment & Sync Ledger
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

function round(num: number): number {
  return Math.round(num);
}
