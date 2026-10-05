import { getStoredToken } from "./auth";

const API_BASE = import.meta.env.VITE_API_URL || "/api";
let sessionCheckPromise: Promise<boolean> | null = null;

async function tokenStillValid(token: string): Promise<boolean> {
  if (!sessionCheckPromise) {
    sessionCheckPromise = fetch(`${API_BASE}/me`, {
      headers: {
        "ngrok-skip-browser-warning": "true",
        Authorization: `Bearer ${token}`,
      },
    })
      .then((response) => response.status !== 401)
      .catch(() => true)
      .finally(() => {
        sessionCheckPromise = null;
      });
  }
  return sessionCheckPromise;
}

/**
 * Authenticated fetch wrapper — attaches Bearer token and handles 401.
 */
async function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const token = getStoredToken();
  const headers: Record<string, string> = {
    ...(options.headers as Record<string, string> || {}),
    "ngrok-skip-browser-warning": "true",
  };
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const res = await fetch(url, { ...options, headers });

  if (res.status === 401) {
    // Only verify and clear if token is present AND matches the current stored token
    const currentToken = getStoredToken();
    if (token && token === currentToken) {
      const stillValid = await tokenStillValid(token);
      if (!stillValid && typeof window !== "undefined" && getStoredToken() === token) {
        localStorage.removeItem("crm_token");
        localStorage.removeItem("crm_user");
        if (window.location.pathname !== "/crm" && window.location.pathname !== "/crm/") {
          window.location.href = "/crm/";
        }
      }
    }
    throw new Error("Session expired. Please log in again.");
  }

  return res;
}

export async function fetchDashboardStats() {
  const res = await authFetch(`${API_BASE}/dashboard/stats`);
  if (!res.ok) throw new Error("Failed to fetch dashboard stats");
  return res.json();
}

export async function fetchLeads(params?: { month?: string; status?: string }) {
  const qs = new URLSearchParams();
  if (params?.month) qs.set("month", params.month);
  if (params?.status) qs.set("status", params.status);
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  const res = await authFetch(`${API_BASE}/leads${suffix}`);
  if (!res.ok) throw new Error("Failed to fetch leads");
  return res.json();
}

/** Admin approves a draft lead → welcome email + indicative quotation. */
export async function approveLead(id: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/approve`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to approve lead");
  return res.json();
}

/** Admin approves a scheduled survey. */
export async function approveSurvey(id: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/approve-survey`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to approve survey");
  return res.json();
}

/** Admin proposes a new date and exact time for a survey before approval. */
export async function proposeSurveyReschedule(id: string, proposedDate: string, proposedTime: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/propose-reschedule`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ proposedDate, proposedTime }),
  });
  if (!res.ok) throw new Error("Failed to propose survey reschedule");
  return res.json();
}

/** Admin assigns a surveyor to a survey lead. */
export async function assignSurveyor(id: string, surveyorName: string, surveyorEmail: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/assign-surveyor`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ surveyorName, surveyorEmail }),
  });
  if (!res.ok) throw new Error("Failed to assign surveyor");
  return res.json();
}

/** Surveyor submits on-site inspection report & uploaded media. */
export async function submitSurveyReport(id: string, reportNotes: string, media: string[]) {
  const res = await authFetch(`${API_BASE}/leads/${id}/submit-survey-report`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ reportNotes, media }),
  });
  if (!res.ok) throw new Error("Failed to submit survey report");
  return res.json();
}

export async function createLead(leadData: any) {
  const res = await authFetch(`${API_BASE}/leads`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(leadData),
  });
  if (!res.ok) throw new Error("Failed to create lead");
  return res.json();
}

export async function updateLead(id: string, leadData: any) {
  const res = await authFetch(`${API_BASE}/leads/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(leadData),
  });
  if (!res.ok) throw new Error("Failed to update lead");
  return res.json();
}

export async function updateLeadStage(id: string, stage: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/stage`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ stage }),
  });
  if (!res.ok) throw new Error("Failed to update lead stage");
  return res.json();
}

export async function deleteLead(id: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete lead");
  return res.json();
}

export async function fetchTrashedLeads() {
  const res = await authFetch(`${API_BASE}/leads/trash`);
  if (!res.ok) throw new Error("Failed to fetch trashed leads");
  return res.json();
}

export async function restoreLead(id: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/restore`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to restore lead");
  return res.json();
}

export async function forceDeleteLead(id: string) {
  const res = await authFetch(`${API_BASE}/leads/${id}/force`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to permanently delete lead");
  return res.json();
}

export async function fetchContacts() {
  const res = await authFetch(`${API_BASE}/contacts`);
  if (!res.ok) throw new Error("Failed to fetch contacts");
  return res.json();
}

export async function createContact(contactData: any) {
  const res = await authFetch(`${API_BASE}/contacts`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(contactData),
  });
  if (!res.ok) throw new Error("Failed to create contact");
  return res.json();
}

export async function fetchCalendarEvents() {
  const res = await authFetch(`${API_BASE}/calendar`);
  if (!res.ok) throw new Error("Failed to fetch calendar events");
  return res.json();
}

export async function createCalendarEvent(eventData: any) {
  const res = await authFetch(`${API_BASE}/calendar`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(eventData),
  });
  if (!res.ok) throw new Error("Failed to create calendar event");
  return res.json();
}

export async function updateCalendarEvent(id: string, eventData: any) {
  const res = await authFetch(`${API_BASE}/calendar/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(eventData),
  });
  if (!res.ok) throw new Error("Failed to update calendar event");
  return res.json();
}

export async function fetchFinanceData(params?: { range?: string; month?: string; service_type?: string }) {
  const query = new URLSearchParams();
  if (params?.range) query.append("range", params.range);
  if (params?.month) query.append("month", params.month);
  if (params?.service_type) query.append("service_type", params.service_type);
  const queryString = query.toString() ? `?${query.toString()}` : "";
  const res = await authFetch(`${API_BASE}/finance${queryString}`);
  if (!res.ok) throw new Error("Failed to fetch finance data");
  return res.json();
}

export async function createTransaction(txData: any) {
  const res = await authFetch(`${API_BASE}/finance/transactions`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(txData),
  });
  if (!res.ok) throw new Error("Failed to create transaction");
  return res.json();
}

export async function fetchIntegrations() {
  const res = await authFetch(`${API_BASE}/integrations`);
  if (!res.ok) throw new Error("Failed to fetch integrations");
  return res.json();
}

export async function toggleIntegration(id: string) {
  const res = await authFetch(`${API_BASE}/integrations/${id}/toggle`, {
    method: "PUT",
  });
  if (!res.ok) throw new Error("Failed to toggle integration");
  return res.json();
}

export async function fetchSettings() {
  const res = await authFetch(`${API_BASE}/settings`);
  if (!res.ok) throw new Error("Failed to fetch settings");
  return res.json();
}

export async function saveSettings(settingsData: any) {
  const res = await authFetch(`${API_BASE}/settings`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(settingsData),
  });
  if (!res.ok) throw new Error("Failed to save settings");
  return res.json();
}

export async function updatePassword(passwordData: { current_password: string; new_password: string }) {
  const res = await authFetch(`${API_BASE}/settings/password`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(passwordData),
  });
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.message || "Failed to update password");
  }
  return data;
}

export async function fetchTeamMembers() {
  const res = await authFetch(`${API_BASE}/team`);
  if (!res.ok) throw new Error("Failed to fetch team members");
  return res.json();
}

export async function createUser(userData: any) {
  const res = await authFetch(`${API_BASE}/users`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(userData),
  });
  if (!res.ok) {
    const err = await res.json();
    throw new Error(err.message || "Failed to create user");
  }
  return res.json();
}

export async function updateUser(id: number | string, userData: any) {
  const res = await authFetch(`${API_BASE}/users/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(userData),
  });
  if (!res.ok) {
    const err = await res.json();
    throw new Error(err.message || "Failed to update user");
  }
  return res.json();
}

export async function deleteUser(id: number | string) {
  const res = await authFetch(`${API_BASE}/users/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) {
    const err = await res.json();
    throw new Error(err.message || "Failed to delete user");
  }
  return res.json();
}

// ── Quotations ───────────────────────────────────────────────────────

export async function fetchQuotations(status?: string) {
  const suffix = status ? `?status=${encodeURIComponent(status)}` : "";
  const res = await authFetch(`${API_BASE}/quotations${suffix}`);
  if (!res.ok) throw new Error("Failed to fetch quotations");
  return res.json();
}

export async function createQuotation(data: any) {
  const res = await authFetch(`${API_BASE}/quotations`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to create quotation");
  return res.json();
}

export async function updateQuotation(id: string, data: any) {
  const res = await authFetch(`${API_BASE}/quotations/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to update quotation");
  return res.json();
}

export async function sendQuotation(id: string) {
  const res = await authFetch(`${API_BASE}/quotations/${id}/send`, {
    method: "POST",
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data?.error || "Failed to send quotation");
  return data;
}

export async function deleteQuotation(id: string) {
  const res = await authFetch(`${API_BASE}/quotations/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete quotation");
  return res.json();
}

// ── Jobs ─────────────────────────────────────────────────────────────

export async function fetchJobs(status?: string) {
  const suffix = status ? `?status=${encodeURIComponent(status)}` : "";
  const res = await authFetch(`${API_BASE}/jobs${suffix}`);
  if (!res.ok) throw new Error("Failed to fetch jobs");
  return res.json();
}

export async function createJob(data: any) {
  const res = await authFetch(`${API_BASE}/jobs`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to create job");
  return res.json();
}

export async function assignJobDriver(
  id: string,
  data: { driver: string; vehicle?: string; event_date?: string; event_time?: string; assignment_type?: "direct" | "approval" }
) {
  const res = await authFetch(`${API_BASE}/jobs/${id}/assign-driver`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to assign driver");
  return res.json();
}

export async function updateJobStatus(
  id: string,
  data: { status: string; notes?: string }
) {
  const res = await authFetch(`${API_BASE}/jobs/${id}/status`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to update job status");
  return res.json();
}

export async function fetchRescheduleRequests() {
  const res = await authFetch(`${API_BASE}/jobs/reschedule-requests`);
  if (!res.ok) throw new Error("Failed to fetch reschedule requests");
  return res.json();
}

export async function approveRescheduleRequest(
  id: string,
  data?: { date?: string; time?: string }
) {
  const res = await authFetch(`${API_BASE}/jobs/reschedule-requests/${id}/approve`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data || {}),
  });
  if (!res.ok) throw new Error("Failed to approve reschedule request");
  return res.json();
}

export async function rejectRescheduleRequest(id: string) {
  const res = await authFetch(`${API_BASE}/jobs/reschedule-requests/${id}/reject`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to dismiss reschedule request");
  return res.json();
}

// ── Outlook Integration ──────────────────────────────────────────────

export async function getOutlookAuthUrl() {
  const res = await authFetch(`${API_BASE}/outlook/auth-url`);
  if (!res.ok) throw new Error("Failed to get Outlook auth URL");
  return res.json();
}

export async function getOutlookStatus() {
  const res = await authFetch(`${API_BASE}/outlook/status`);
  if (!res.ok) throw new Error("Failed to get Outlook status");
  return res.json();
}

export async function disconnectOutlook() {
  const res = await authFetch(`${API_BASE}/outlook/disconnect`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to disconnect Outlook");
  return res.json();
}

export async function fetchOutlookEmails(
  page = 1,
  perPage = 30,
  search = "",
  filter = "all"
) {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
    search,
    filter,
  });
  const res = await authFetch(`${API_BASE}/outlook/emails?${params}`);
  if (!res.ok) throw new Error("Failed to fetch Outlook emails");
  return res.json();
}

export async function syncOutlookEmails() {
  const res = await authFetch(`${API_BASE}/outlook/sync`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to sync Outlook emails");
  return res.json();
}

export async function sendOutlookEmail(
  to: string,
  subject: string,
  body: string
) {
  const res = await authFetch(`${API_BASE}/outlook/send`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ to, subject, body }),
  });
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data?.error || "Failed to send email via Outlook");
  }
  return data;
}

export interface LocationGeocode {
  postcode: string;
  latitude: number;
  longitude: number;
  address?: string;
  district?: string;
  region?: string;
  country?: string;
}

/**
 * Geocodes UK postcode using OS Places API (if apiKey provided) or free Postcodes.io API fallback.
 */
export async function lookupPostcode(postcode: string, apiKey?: string): Promise<LocationGeocode | null> {
  const cleanPostcode = postcode.trim().toUpperCase();
  if (!cleanPostcode) return null;

  // 1. Try Ordnance Survey OS Places API endpoint if API key provided
  if (apiKey) {
    try {
      const url = `https://api.os.uk/search/places/v1/postcode?postcode=${encodeURIComponent(cleanPostcode)}&key=${apiKey}&output_srs=EPSG:4326`;
      const res = await fetch(url);
      if (res.ok) {
        const data = await res.json();
        if (data.results && data.results.length > 0) {
          const dpa = data.results[0].DPA;
          return {
            postcode: dpa.POSTCODE || cleanPostcode,
            latitude: Number(dpa.LATITUDE),
            longitude: Number(dpa.LONGITUDE),
            address: dpa.ADDRESS || `${dpa.BUILDING_NAME || ''} ${dpa.THOROUGHFARE_NAME || ''}, ${dpa.POST_TOWN}`.trim(),
            district: dpa.POST_TOWN,
          };
        }
      }
    } catch (err) {
      console.warn("OS Places API lookup failed, falling back to open Postcodes.io:", err);
    }
  }

  // 2. Free Open Endpoint Fallback: postcodes.io
  try {
    const formatted = cleanPostcode.replace(/\s+/g, "");
    const res = await fetch(`https://api.postcodes.io/postcodes/${formatted}`);
    if (res.ok) {
      const data = await res.json();
      if (data.status === 200 && data.result) {
        const r = data.result;
        return {
          postcode: r.postcode,
          latitude: r.latitude,
          longitude: r.longitude,
          district: r.admin_district,
          region: r.region,
          country: r.country,
        };
      }
    }
  } catch (err) {
    console.error("Postcode lookup error:", err);
  }

  return null;
}

// ── Survey Day Reminders ─────────────────────────────────────────────

export interface SurveyReminder {
  id: number;
  type: "1_day" | "6_hours" | "1_hour" | "15_min";
  label: string;
  scheduledAt: string | null;
  status: "pending" | "sent" | "skipped" | "stopped";
  sentAt: string | null;
}

export async function fetchSurveyReminders(leadId: string): Promise<SurveyReminder[]> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/survey-reminders`);
  if (!res.ok) throw new Error("Failed to fetch survey reminders");
  return res.json();
}

export async function skipSurveyReminder(leadId: string, reminderId: number): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/survey-reminders/${reminderId}/skip`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to skip reminder");
}

export async function stopSurveyReminders(leadId: string): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/survey-reminders/stop`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to stop reminders");
}

export async function updateSurveyReminder(leadId: string, reminderId: number, scheduledAt: string): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/survey-reminders/${reminderId}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ scheduledAt }),
  });
  if (!res.ok) throw new Error("Failed to update survey reminder");
}

// ── Quotation Reminders ──────────────────────────────────────────────

export interface QuotationReminder {
  id: number;
  label: string;
  scheduledAt: string | null;
  status: "pending" | "sent" | "skipped" | "stopped";
  sentAt: string | null;
}

export async function fetchQuotationReminders(leadId: string): Promise<QuotationReminder[]> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/quotation-reminders`);
  if (!res.ok) throw new Error("Failed to fetch quotation reminders");
  return res.json();
}

export async function skipQuotationReminder(leadId: string, reminderId: number): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/quotation-reminders/${reminderId}/skip`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to skip quotation reminder");
}

export async function stopQuotationReminders(leadId: string): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/quotation-reminders/stop`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to stop quotation reminders");
}

export async function updateQuotationReminder(leadId: string, reminderId: number, scheduledAt: string): Promise<void> {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/quotation-reminders/${reminderId}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ scheduledAt }),
  });
  if (!res.ok) throw new Error("Failed to update quotation reminder");
}

// ── Live Video Call Survey API ───────────────────────────────────────

export async function startVideoCall(leadId: string, payload: Record<string, unknown> = {}) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/video-call/start`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ lead_id: leadId, ...payload }),
  });
  if (!res.ok) throw new Error("Failed to initiate video call");
  return res.json();
}

export async function fetchVideoCallStatus(leadId: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/video-call/status`);
  if (!res.ok) throw new Error("Failed to fetch call status");
  return res.json();
}

export async function adminVideoCallAction(leadId: string, action: "accept" | "decline" | "end") {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/video-call/action`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ lead_id: leadId, action }),
  });
  if (!res.ok) throw new Error(`Failed to ${action} video call`);
  return res.json();
}

export async function saveVideoCallNotes(leadId: string, notes: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/video-call/notes`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ notes }),
  });
  if (!res.ok) throw new Error("Failed to save video call notes");
  return res.json();
}

export async function syncVideoCallStatus(leadId: string, status?: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/video-call/status`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ status }),
  });
  if (!res.ok) throw new Error("Failed to sync call status");
  return res.json();
}

export async function respondVideoCall(leadId: string, action: string) {
  const res = await authFetch(`${API_BASE}/customer/video-call/respond`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ lead_id: leadId, action }),
  });
  if (!res.ok) throw new Error("Failed to respond to video call");
  return res.json();
}

export async function sendAppCredentials(leadId: string, password?: string, message?: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/send-app-credentials`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ password, message }),
  });
  if (!res.ok) throw new Error("Failed to send app credentials");
  return res.json();
}

// ── Email Template Library API ───────────────────────────────────────

export async function fetchEmailTemplates() {
  const res = await authFetch(`${API_BASE}/email-templates`);
  if (!res.ok) throw new Error("Failed to fetch email templates catalog");
  return res.json();
}

export async function previewLeadTemplate(leadId: string, templateKey: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/preview-template`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ templateKey }),
  });
  if (!res.ok) throw new Error("Failed to render email preview");
  return res.json();
}

export async function sendLeadTemplateEmail(leadId: string, templateKey: string, subject?: string) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/send-template-email`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ templateKey, subject }),
  });
  if (!res.ok) throw new Error("Failed to send template email");
  return res.json();
}

// ── Slack-Style Team Chat API ─────────────────────────────────────────

export async function fetchChatChannels() {
  const res = await authFetch(`${API_BASE}/chat/channels`);
  if (!res.ok) throw new Error("Failed to fetch channels");
  return res.json();
}

export async function fetchChatMembers() {
  const res = await authFetch(`${API_BASE}/chat/members`);
  if (!res.ok) throw new Error("Failed to fetch team members");
  return res.json();
}

export async function fetchChatMessages(params: { channelId?: number; receiverId?: number; afterId?: number; beforeId?: number; limit?: number }) {
  const qs = new URLSearchParams();
  if (params.channelId) qs.set("channel_id", String(params.channelId));
  if (params.receiverId) qs.set("receiver_id", String(params.receiverId));
  if (params.afterId) qs.set("after_id", String(params.afterId));
  if (params.beforeId) qs.set("before_id", String(params.beforeId));
  if (params.limit) qs.set("limit", String(params.limit));
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  const res = await authFetch(`${API_BASE}/chat/messages${suffix}`);
  if (!res.ok) throw new Error("Failed to fetch chat messages");
  return res.json();
}

export async function sendChatMessage(payload: { channelId?: number; receiverId?: number; message: string }) {
  const res = await authFetch(`${API_BASE}/chat/messages`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error("Failed to send message");
  return res.json();
}

export async function sendChatMessageWithAttachment(payload: FormData) {
  const res = await authFetch(`${API_BASE}/chat/messages`, {
    method: "POST",
    // Do NOT set Content-Type header when sending FormData; browser will set it with the correct boundary
    body: payload,
  });
  if (!res.ok) throw new Error("Failed to send message with attachment");
  return res.json();
}

export async function updateChatMessage(id: number, message: string) {
  const res = await authFetch(`${API_BASE}/chat/messages/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message }),
  });
  if (!res.ok) throw new Error("Failed to update message");
  return res.json();
}

export async function deleteChatMessage(id: number) {
  const res = await authFetch(`${API_BASE}/chat/messages/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete message");
  return res.json();
}

export async function createChatChannel(params: { displayName: string; description?: string; isPrivate?: boolean; members?: number[] }) {
  const res = await authFetch(`${API_BASE}/chat/channels`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(params),
  });
  if (!res.ok) throw new Error("Failed to create group channel");
  return res.json();
}

export async function deleteChatChannel(channelId: number) {
  const res = await authFetch(`${API_BASE}/chat/channels/${channelId}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete group channel");
  return res.json();
}

// ── Direct-message audio/video calls ────────────────────────────────────────

export async function startChatCall(recipientId: number, isVideo: boolean) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 30000);
  let res: Response;
  try {
    res = await authFetch(`${API_BASE}/chat/calls/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ recipient_id: recipientId, is_video: isVideo }),
      signal: controller.signal,
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new Error("Call setup timed out after 30 seconds. Please try again.");
    }
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.error || "Failed to start chat call");
  }
  return res.json();
}

export async function fetchIncomingChatCall() {
  const res = await authFetch(`${API_BASE}/chat/calls/incoming`);
  if (!res.ok) throw new Error("Failed to check incoming chat calls");
  return res.json();
}

export async function chatCallAction(callId: number, action: "accept" | "decline" | "end") {
  const res = await authFetch(`${API_BASE}/chat/calls/${callId}/action`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action }),
  });
  if (!res.ok) throw new Error(`Failed to ${action} chat call`);
  return res.json();
}

/** Caller polls this to detect when recipient accepts (status -> in_progress) */
export async function fetchChatCallStatus(callId: number) {
  const res = await authFetch(`${API_BASE}/chat/calls/${callId}/status`);
  if (!res.ok) throw new Error("Failed to fetch chat call status");
  return res.json();
}

// ── Video Call & Video Storage API ──────────────────────────────────────────

export async function createVideoCallRoom(leadId?: string, leadName?: string) {
  const res = await authFetch(`${API_BASE}/video-calls/create-room`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ lead_id: leadId, lead_name: leadName }),
  });
  if (!res.ok) throw new Error("Failed to create video call room");
  return res.json();
}

export async function fetchSurveyVideos(leadId?: string) {
  const suffix = leadId ? `?lead_id=${leadId}` : "";
  const res = await authFetch(`${API_BASE}/videos${suffix}`);
  if (!res.ok) throw new Error("Failed to fetch survey videos");
  return res.json();
}

export async function uploadSurveyVideo(formData: FormData) {
  const token = localStorage.getItem("crm_auth_token");
  const res = await fetch(`${API_BASE}/videos/upload`, {
    method: "POST",
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    body: formData,
  });
  if (!res.ok) throw new Error("Failed to upload survey video file");
  return res.json();
}

export async function deleteSurveyVideo(id: number) {
  const res = await authFetch(`${API_BASE}/videos/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete video");
  return res.json();
}

// ── Survey Media (Flutter App Uploads) ───────────────────────────────────────

export interface SurveyMediaItem {
  id: number;
  leadId: string;
  type: "image" | "video" | "note" | "document";
  fileUrl: string | null;
  fileName: string | null;
  fileSize: number | null;
  mimeType: string | null;
  notes: string | null;
  caption: string | null;
  surveyorName: string | null;
  createdAt: string;
}

/** Fetch all survey media items for a given lead */
export async function fetchSurveyMedia(leadId: string): Promise<SurveyMediaItem[]> {
  const res = await fetch(`${API_BASE}/survey/media?lead_id=${encodeURIComponent(leadId)}`);
  if (!res.ok) throw new Error("Failed to fetch survey media");
  return res.json();
}

/** Delete a survey media item by ID */
export async function deleteSurveyMedia(id: number): Promise<void> {
  const res = await fetch(`${API_BASE}/survey/media/${id}`, { method: "DELETE" });
  if (!res.ok) throw new Error("Failed to delete media");
}

/** Upload media (photo, video, document/PDF/text, note) for a lead */
export async function uploadSurveyMedia(formData: FormData): Promise<SurveyMediaItem> {
  const res = await fetch(`${API_BASE}/survey/upload-media`, {
    method: "POST",
    body: formData,
  });
  if (!res.ok) throw new Error("Failed to upload survey media");
  return res.json();
}

// INVOICES
export async function fetchInvoices() {
  const res = await authFetch(`${API_BASE}/invoices`);
  if (!res.ok) throw new Error("Failed to fetch invoices");
  return res.json();
}
export async function createInvoice(data: any) {
  const res = await authFetch(`${API_BASE}/invoices`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to create invoice");
  return res.json();
}
export async function updateInvoice(id: string, data: any) {
  const res = await authFetch(`${API_BASE}/invoices/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to update invoice");
  return res.json();
}
export async function sendInvoice(id: string) {
  const res = await authFetch(`${API_BASE}/invoices/${id}/send`, { method: "POST" });
  if (!res.ok) throw new Error("Failed to send invoice");
  return res.json();
}
export async function recordInvoicePayment(id: string, paymentData?: any) {
  const res = await authFetch(`${API_BASE}/invoices/${id}/record-payment`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(paymentData || {}),
  });
  if (!res.ok) throw new Error("Failed to record invoice payment");
  return res.json();
}

export async function convertQuotationToInvoice(quotationId: string) {
  const res = await authFetch(`${API_BASE}/quotations/${quotationId}/convert-to-invoice`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to convert quotation to invoice");
  return res.json();
}

export async function updateTransaction(id: string, data: any) {
  const res = await authFetch(`${API_BASE}/finance/transactions/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error("Failed to update transaction");
  return res.json();
}

export async function deleteTransaction(id: string) {
  const res = await authFetch(`${API_BASE}/finance/transactions/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error("Failed to delete transaction");
  return res.json();
}

// ── Notifications API ───────────────────────────────────────────────────────

export async function fetchAppNotifications() {
  const res = await authFetch(`${API_BASE}/notifications`);
  if (!res.ok) throw new Error("Failed to fetch notifications");
  return res.json();
}

export async function markNotificationAsRead(id: number) {
  const res = await authFetch(`${API_BASE}/notifications/${id}/read`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to mark notification as read");
  return res.json();
}

export async function markAllNotificationsAsRead() {
  const res = await authFetch(`${API_BASE}/notifications/read-all`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to mark all notifications as read");
  return res.json();
}

// ── Drivers API ─────────────────────────────────────────────────────────────

export async function fetchDrivers() {
  const res = await authFetch(`${API_BASE}/drivers`);
  if (!res.ok) throw new Error("Failed to fetch drivers");
  return res.json();
}

export async function assignDriver(leadId: string, driverData: { driverId?: string; driverName?: string; driverEmail?: string; driverPhone?: string; vehicle?: string; event_date?: string; event_time?: string; notes?: string }) {
  const res = await authFetch(`${API_BASE}/leads/${leadId}/assign-driver`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(driverData),
  });
  if (!res.ok) throw new Error("Failed to assign driver & schedule job");
  return res.json();
}

// ── Call Recording & Speech-to-Text Transcription API ───────────────────────

export async function fetchCalls(params?: { contact_id?: string; status?: string; q?: string }) {
  const query = new URLSearchParams();
  if (params?.contact_id) query.set("contact_id", params.contact_id);
  if (params?.status) query.set("status", params.status);
  if (params?.q) query.set("q", params.q);

  const res = await authFetch(`${API_BASE}/calls?${query.toString()}`);
  if (!res.ok) throw new Error("Failed to fetch call records");
  return res.json();
}

export async function fetchCall(id: number) {
  const res = await authFetch(`${API_BASE}/calls/${id}`);
  if (!res.ok) throw new Error("Failed to fetch call details");
  return res.json();
}

export async function initiateCall(payload: { contact_id?: string; direction?: string }) {
  const res = await authFetch(`${API_BASE}/calls`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error("Failed to initiate call session");
  return res.json();
}

export async function uploadCallRecording(callId: number, formData: FormData) {
  const token = getStoredToken();
  const res = await fetch(`${API_BASE}/calls/${callId}/recording`, {
    method: "POST",
    headers: {
      "ngrok-skip-browser-warning": "true",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });
  if (!res.ok) throw new Error("Failed to upload call recording");
  return res.json();
}

export async function retryCallTranscription(callId: number) {
  const res = await authFetch(`${API_BASE}/calls/${callId}/retry`, {
    method: "POST",
  });
  if (!res.ok) throw new Error("Failed to retry transcription");
  return res.json();
}


