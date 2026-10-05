import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  fetchLeads,
  fetchDashboardStats,
  fetchJobs,
  fetchQuotations,
  fetchContacts,
  fetchCalendarEvents,
  createLead,
  approveLead,
  deleteLead,
  fetchTrashedLeads,
  restoreLead,
  forceDeleteLead,
  approveSurvey,
  proposeSurveyReschedule,
  assignSurveyor,
  submitSurveyReport,
  assignJobDriver,
  createJob,
  updateJobStatus,
  fetchRescheduleRequests,
  approveRescheduleRequest,
  rejectRescheduleRequest,
  createQuotation,
  updateQuotation,
  sendQuotation,
  deleteQuotation,
  createContact,
  fetchSurveyReminders,
  skipSurveyReminder,
  stopSurveyReminders,
  updateSurveyReminder,
  fetchQuotationReminders,
  skipQuotationReminder,
  stopQuotationReminders,
  updateQuotationReminder,
  startVideoCall,
  syncVideoCallStatus,
  sendAppCredentials,
  fetchInvoices,
  updateInvoice,
  sendInvoice,
  fetchTeamMembers,
  fetchDrivers,
} from "./api";

export const STALE_TIME = 1000 * 60 * 2; // 2 minutes cache stale time
export const QUERY_CONFIG = {
  staleTime: STALE_TIME,
  gcTime: 1000 * 60 * 10, // 10 minutes in memory
  refetchOnWindowFocus: true,
  refetchOnMount: true,
};

export function useAssignableDriversQuery() {
  return useQuery({
    queryKey: ["assignable-drivers"],
    queryFn: async () => {
      const results = await Promise.allSettled([fetchDrivers(), fetchTeamMembers()]);
      const drivers = results[0].status === "fulfilled" && Array.isArray(results[0].value) ? results[0].value : [];
      const members = results[1].status === "fulfilled" && Array.isArray(results[1].value) ? results[1].value : [];

      const userMap = new Map<number | string, any>();

      drivers.forEach((d: any) => {
        if (d && d.id) {
          userMap.set(d.id, { ...d, role: d.role || "driver" });
        }
      });

      members.forEach((m: any) => {
        if (m && m.id) {
          if (!userMap.has(m.id)) {
            userMap.set(m.id, m);
          } else {
            userMap.set(m.id, { ...userMap.get(m.id), ...m });
          }
        }
      });

      const list = Array.from(userMap.values());
      list.sort((a, b) => {
        const order = (role: string) => (role === "driver" ? 0 : role === "staff" ? 1 : role === "admin" ? 2 : 3);
        const diff = order(a.role) - order(b.role);
        if (diff !== 0) return diff;
        return (a.name || "").localeCompare(b.name || "");
      });

      return list;
    },
    staleTime: 1000 * 60 * 5,
    gcTime: 1000 * 60 * 15,
  });
}

export function useTeamMembersQuery() {
  return useQuery({
    queryKey: ["team-members"],
    queryFn: async () => {
      try {
        const res = await fetchTeamMembers();
        if (Array.isArray(res) && res.length > 0) return res;
      } catch (err) {
        console.warn("fetchTeamMembers failed, falling back to drivers:", err);
      }
      const drivers = await fetchDrivers();
      return Array.isArray(drivers) ? drivers : [];
    },
    staleTime: 1000 * 60 * 5,
    gcTime: 1000 * 60 * 15,
  });
}

// Query Hooks
export function useLeadsQuery(params?: { month?: string; status?: string }) {
  return useQuery({
    queryKey: ["leads", params?.month || "all", params?.status || "all"],
    queryFn: () => fetchLeads(params),
    ...QUERY_CONFIG,
  });
}

export function useAllLeadsQuery() {
  return useQuery({
    queryKey: ["leads", "all", "all"],
    queryFn: () => fetchLeads(),
    ...QUERY_CONFIG,
  });
}

export function useTrashedLeadsQuery() {
  return useQuery({
    queryKey: ["trashed-leads"],
    queryFn: () => fetchTrashedLeads(),
    ...QUERY_CONFIG,
  });
}

export function useRestoreLeadMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => restoreLead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["trashed-leads"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useForceDeleteLeadMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => forceDeleteLead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["trashed-leads"] });
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useDashboardStatsQuery() {
  return useQuery({
    queryKey: ["dashboard-stats"],
    queryFn: fetchDashboardStats,
    ...QUERY_CONFIG,
  });
}

export function useJobsQuery(statusFilter?: string) {
  return useQuery({
    queryKey: ["jobs", statusFilter || "all"],
    queryFn: async () => {
      const data = await fetchJobs(statusFilter !== "all" ? statusFilter : undefined);
      const list = Array.isArray(data) ? data : data?.jobs;
      return Array.isArray(list) ? list : [];
    },
    ...QUERY_CONFIG,
  });
}

export function useCreateJobMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => createJob(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
    },
  });
}

export function useQuotationsQuery() {
  return useQuery({
    queryKey: ["quotations"],
    queryFn: () => fetchQuotations(),
    ...QUERY_CONFIG,
  });
}

export function useContactsQuery() {
  return useQuery({
    queryKey: ["contacts"],
    queryFn: fetchContacts,
    ...QUERY_CONFIG,
  });
}

export function useCalendarEventsQuery() {
  return useQuery({
    queryKey: ["calendar-events"],
    queryFn: async () => {
      const res = await fetchCalendarEvents();
      return Array.isArray(res) ? res : [];
    },
    ...QUERY_CONFIG,
  });
}

// Mutation Hooks with Auto-Cache Invalidation
export function useApproveLeadMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => approveLead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
      queryClient.invalidateQueries({ queryKey: ["outlook-emails"] });
    },
  });
}

export function useCreateLeadMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => createLead(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useDeleteLeadMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => deleteLead(id),
    onMutate: async (id: string) => {
      // Cancel refetches so they don't overwrite optimistic update
      await queryClient.cancelQueries({ queryKey: ["leads"] });

      // Snapshot previous leads cache value
      const previousQueries = queryClient.getQueriesData<any[]>({ queryKey: ["leads"] });

      // Optimistically remove the lead from cache instantly
      queryClient.setQueriesData<any[]>({ queryKey: ["leads"] }, (old) => {
        if (!Array.isArray(old)) return old;
        return old.filter((item) => item.id !== id);
      });

      return { previousQueries };
    },
    onError: (_err, _id, context) => {
      // Rollback on error
      if (context?.previousQueries) {
        context.previousQueries.forEach(([key, data]) => {
          queryClient.setQueryData(key, data);
        });
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["contacts"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useApproveSurveyMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => approveSurvey(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useProposeSurveyRescheduleMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, proposedDate, proposedTime }: { id: string; proposedDate: string; proposedTime: string }) =>
      proposeSurveyReschedule(id, proposedDate, proposedTime),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    },
  });
}

export function useAssignSurveyorMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, surveyorName, surveyorEmail }: { id: string; surveyorName: string; surveyorEmail: string }) =>
      assignSurveyor(id, surveyorName, surveyorEmail),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    },
  });
}

export function useSubmitSurveyReportMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reportNotes, media }: { id: string; reportNotes: string; media: string[] }) =>
      submitSurveyReport(id, reportNotes, media),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    },
  });
}

export function useAssignJobDriverMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      driver,
      vehicle,
      event_date,
      event_time,
      assignment_type,
    }: {
      id: string;
      driver?: string;
      vehicle?: string;
      event_date?: string;
      event_time?: string;
      assignment_type?: "direct" | "approval";
    }) => assignJobDriver(id, { driver: driver ?? "", vehicle, event_date, event_time, assignment_type }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useUpdateJobStatusMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      updateJobStatus(id, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useRescheduleRequestsQuery() {
  return useQuery({
    queryKey: ["job-reschedule-requests"],
    queryFn: fetchRescheduleRequests,
    refetchInterval: 10000,
  });
}

export function useApproveRescheduleMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, date, time }: { id: string; date?: string; time?: string }) =>
      approveRescheduleRequest(id, { date, time }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["job-reschedule-requests"] });
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["calendar"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
    },
  });
}

export function useRejectRescheduleMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => rejectRescheduleRequest(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["jobs"] });
      queryClient.invalidateQueries({ queryKey: ["job-reschedule-requests"] });
    },
  });
}

// ── Survey Day Reminder Hooks ────────────────────────────────────────

export function useSurveyRemindersQuery(leadId: string | null | undefined) {
  return useQuery({
    queryKey: ["survey-reminders", leadId],
    queryFn: () => fetchSurveyReminders(leadId!),
    enabled: !!leadId,
    staleTime: 0, // always fresh so skip/stop reflects instantly
  });
}

export function useSkipSurveyReminderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, reminderId }: { leadId: string; reminderId: number }) =>
      skipSurveyReminder(leadId, reminderId),
    onSuccess: (_data, { leadId }) => {
      queryClient.invalidateQueries({ queryKey: ["survey-reminders", leadId] });
    },
  });
}

export function useStopSurveyRemindersMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (leadId: string) => stopSurveyReminders(leadId),
    onSuccess: (_data, leadId) => {
      queryClient.invalidateQueries({ queryKey: ["survey-reminders", leadId] });
    },
  });
}

export function useUpdateSurveyReminderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, reminderId, scheduledAt }: { leadId: string; reminderId: number; scheduledAt: string }) =>
      updateSurveyReminder(leadId, reminderId, scheduledAt),
    onSuccess: (_data, { leadId }) => {
      queryClient.invalidateQueries({ queryKey: ["survey-reminders", leadId] });
    },
  });
}

// ── Quotation Reminder Hooks ──────────────────────────────────────────

export function useQuotationRemindersQuery(leadId: string | null | undefined) {
  return useQuery({
    queryKey: ["quotation-reminders", leadId],
    queryFn: () => fetchQuotationReminders(leadId!),
    enabled: !!leadId,
    staleTime: 0,
  });
}

export function useSkipQuotationReminderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, reminderId }: { leadId: string; reminderId: number }) =>
      skipQuotationReminder(leadId, reminderId),
    onSuccess: (_data, { leadId }) => {
      queryClient.invalidateQueries({ queryKey: ["quotation-reminders", leadId] });
    },
  });
}

export function useStopQuotationRemindersMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (leadId: string) => stopQuotationReminders(leadId),
    onSuccess: (_data, leadId) => {
      queryClient.invalidateQueries({ queryKey: ["quotation-reminders", leadId] });
    },
  });
}

export function useUpdateQuotationReminderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, reminderId, scheduledAt }: { leadId: string; reminderId: number; scheduledAt: string }) =>
      updateQuotationReminder(leadId, reminderId, scheduledAt),
    onSuccess: (_data, { leadId }) => {
      queryClient.invalidateQueries({ queryKey: ["quotation-reminders", leadId] });
    },
  });
}

// ── Live Video Call Survey Hooks ─────────────────────────────────────

export function useStartVideoCallMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (leadId: string) => startVideoCall(leadId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    },
  });
}

export function useSyncVideoCallStatusMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, status }: { leadId: string; status?: string }) =>
      syncVideoCallStatus(leadId, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
    },
  });
}

export function useSendAppCredentialsMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, password, message }: { leadId: string; password?: string; message?: string }) =>
      sendAppCredentials(leadId, password, message),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["leads"] });
      queryClient.invalidateQueries({ queryKey: ["outlook-emails"] });
    },
  });
}

// INVOICES
export function useInvoicesQuery() {
  return useQuery({ queryKey: ["invoices"], queryFn: () => fetchInvoices() });
}
export function useUpdateInvoiceMutation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => updateInvoice(id, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["invoices"] }),
  });
}
export function useSendInvoiceMutation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => sendInvoice(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["invoices"] }),
  });
}
