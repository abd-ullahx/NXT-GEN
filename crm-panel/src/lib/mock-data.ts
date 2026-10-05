import logo from "@/assets/next-gen-logo.png.asset.json";

export const companyLogo = logo.url;

export type LeadStage =
  | "New"
  | "Welcome Email"
  | "Quotation"
  | "Quotation Approved"
  | "Survey"
  | "Survey Approved"
  | "Book a Survey"
  | "Job"
  | "Completed"
  | "Invoice";
export type LeadStatus =
  | "draft"
  | "new"
  | "contacted"
  | "quoted"
  | "quote-approved"
  | "survey-booked"
  | "survey-approved"
  | "job-booked"
  | "won"
  | "not-completed"
  | "lost";
export type LeadSource =
  | "Getamover"
  | "Konnectyou"
  | "Website Form"
  | "Social Platforms"
  | "Calls"
  | "Refer"
  | "PinLocal"
  | "CompareMyMove"
  | "reallymoving"
  | "Facebook"
  | "Instagram"
  | "Google Ads"
  | "Referral"
  | "Phone"
  | "Outlook"
  | "WebForm";

export interface Lead {
  id: string;
  name: string;
  email: string;
  phone: string;
  source: LeadSource;
  status: LeadStatus;
  stage?: LeadStage;
  approvedAt?: string;
  welcomeEmailSentAt?: string;
  surveyEmailSentAt?: string;
  surveyRequestedAt?: string;
  surveyBookedAt?: string;
  surveyApprovedAt?: string;
  quotationSentAt?: string;
  quotationStatus?: string;
  quotationApprovedAt?: string;
  invoiceIssuedAt?: string;
  reminderStage?: number;
  reminderNextAt?: string;
  reminderContext?: string;
  lastResponseAt?: string;
  surveyRequestedDate?: string;
  surveyRequestedTimeRange?: string;
  surveyNotes?: string;
  surveyStatus?: string;
  surveyType?: string;
  moveType: string;
  from: string;
  to: string;
  moveDate: string;
  estValue: number;
  aiScore: number;
  priority: "Hot" | "Warm" | "Cold";
  createdAt?: string;
  createdAgo: string;
}

export const leadSources: LeadSource[] = [
  "Getamover",
  "Konnectyou",
  "Website Form",
  "Social Platforms",
  "Calls",
  "Refer",
];

export const statusMeta: Record<LeadStatus, { label: string; tone: string }> = {
  draft: { label: "Draft", tone: "text-muted-foreground" },
  new: { label: "New", tone: "text-chart-3" },
  contacted: { label: "Contacted", tone: "text-warning" },
  quoted: { label: "Quoted", tone: "text-chart-5" },
  "quote-approved": { label: "Quote Approved", tone: "text-gold" },
  "survey-booked": { label: "Survey Booked", tone: "text-gold" },
  "survey-approved": { label: "Survey Approved", tone: "text-chart-3" },
  "job-booked": { label: "Job Booked", tone: "text-chart-5" },
  won: { label: "Won", tone: "text-success" },
  "not-completed": { label: "Not Completed", tone: "text-warning" },
  lost: { label: "Lost", tone: "text-destructive" },
};

export interface JobEvent {
  id: string;
  title: string;
  type: "Job" | "Survey" | "Call" | "Maintenance" | "Reminder";
  status?: string;
  date: string; // yyyy-mm-dd
  time: string;
  driver: string;
  vehicle: string;
  location: string;
  leadId?: string;
}

export const eventTypeMeta: Record<JobEvent["type"], { dot: string; chip: string }> = {
  Job: { dot: "bg-gold", chip: "bg-gold/15 text-gold border-gold/30" },
  Survey: { dot: "bg-chart-3", chip: "bg-chart-3/15 text-chart-3 border-chart-3/30" },
  Call: { dot: "bg-chart-5", chip: "bg-chart-5/15 text-chart-5 border-chart-5/30" },
  Maintenance: { dot: "bg-warning", chip: "bg-warning/15 text-warning border-warning/30" },
  Reminder: { dot: "bg-chart-2", chip: "bg-chart-2/15 text-chart-2 border-chart-2/30" },
};

export interface Contact {
  id: string;
  name: string;
  email: string;
  phone: string;
  type: "Customer" | "Driver" | "Supplier";
  moves: number;
  lifetimeValue: number;
  status: "Active" | "Lead" | "Past";
}

export const revenueByMonth = [
  { month: "Jan", revenue: 38200, expenses: 21400 },
  { month: "Feb", revenue: 42100, expenses: 22800 },
  { month: "Mar", revenue: 51800, expenses: 26300 },
  { month: "Apr", revenue: 47600, expenses: 24900 },
  { month: "May", revenue: 58900, expenses: 28100 },
  { month: "Jun", revenue: 64300, expenses: 29700 },
];

export const expenseBreakdown = [
  { name: "Fleet & Fuel", value: 38, color: "var(--color-gold)" },
  { name: "Wages", value: 34, color: "var(--color-chart-2)" },
  { name: "Packing Materials", value: 14, color: "var(--color-chart-3)" },
  { name: "Marketing", value: 9, color: "var(--color-chart-5)" },
  { name: "Insurance", value: 5, color: "var(--color-destructive)" },
];

export interface Transaction {
  id: string;
  date: string;
  label: string;
  category: string;
  type: "income" | "expense";
  amount: number;
}

export const integrations = [
  { id: "outlook", name: "Microsoft Outlook", desc: "Two-way email sync, auto-capture leads from inbox.", category: "Email", connected: true, color: "#0F6CBD" },
  { id: "n8n", name: "n8n Automations", desc: "Route emails to AI, build draft replies, automate workflows.", category: "Automation", connected: true, color: "#EA4B71" },
  { id: "openai", name: "ChatGPT (OpenAI)", desc: "AI lead scoring, email drafting and summaries.", category: "AI", connected: true, color: "#10A37F" },
  { id: "claude", name: "Claude (Anthropic)", desc: "AI assistant for replies and document analysis.", category: "AI", connected: false, color: "#D97757" },
  { id: "pinlocal", name: "PinLocal", desc: "Import removal leads automatically.", category: "Leads", connected: true, color: "#C9A84C" },
  { id: "comparemymove", name: "CompareMyMove", desc: "Import comparison leads automatically.", category: "Leads", connected: true, color: "#3B82F6" },
  { id: "reallymoving", name: "reallymoving", desc: "Import quote-request leads automatically.", category: "Leads", connected: false, color: "#22C55E" },
  { id: "whatsapp", name: "WhatsApp Business", desc: "Two-way messaging with customers.", category: "Messaging", connected: false, color: "#25D366" },
  { id: "stripe", name: "Stripe Payments", desc: "Collect deposits and final payments.", category: "Payments", connected: false, color: "#635BFF" },
];

export const kpis = [
  { label: "Pipeline Value", value: "£186,400", delta: "+12.4%", up: true },
  { label: "Active Leads", value: "47", delta: "+8 this week", up: true },
  { label: "Jobs This Month", value: "23", delta: "+3 vs last", up: true },
  { label: "Won Revenue (30d)", value: "£64,300", delta: "+9.2%", up: true },
];
