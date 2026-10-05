import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid } from "recharts";
import { ArrowUpRight, TrendingUp, Phone, CalendarClock, Sparkles, Truck, Database } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { fetchDashboardStats } from "@/lib/api";
import { statusMeta, eventTypeMeta, type Lead, type JobEvent } from "@/lib/mock-data";
import { useAuth } from "@/lib/auth";

const emptyKpis = [
  { label: "Pipeline Value", value: "£0", delta: "—", up: true },
  { label: "Active Leads", value: "0", delta: "—", up: true },
  { label: "Jobs This Month", value: "0", delta: "—", up: true },
  { label: "Won Revenue (30d)", value: "£0", delta: "—", up: true },
];

import { useDashboardStatsQuery } from "@/lib/queries";

export const Route = createFileRoute("/app/dashboard")({
  head: () => ({ meta: [{ title: "Dashboard — Next Gen Relocation CRM" }] }),
  component: Dashboard,
});

function Dashboard() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { data: statsData, isLoading: loading } = useDashboardStatsQuery();

  // Redirect surveyor users to their dedicated Surveyor Portal
  useEffect(() => {
    if (user?.role === "surveyor") {
      navigate({ to: "/app/surveyor", search: {} as any });
    }
  }, [user, navigate]);

  const data = statsData || {
    kpis: emptyKpis,
    revenueByMonth: [],
    leads: [],
    jobEvents: [],
    database: "MySQL",
  };

  const kpis = data.kpis || emptyKpis;
  const revenueByMonth = data.revenueByMonth || [];
  const jobEvents: JobEvent[] = Array.isArray(data.jobEvents) ? data.jobEvents : [];
  const leads: Lead[] = Array.isArray(data.leads) ? data.leads : [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <Sparkles className="h-3.5 w-3.5" /> Command Centre
            <span className="ml-2 flex items-center gap-1 rounded-full bg-gold/15 px-2 py-0.5 text-[10px] text-gold border border-gold/30">
              <Database className="h-3 w-3" /> {data.database || "MySQL Connected"}
            </span>
          </div>
          <h1 className="mt-1 font-display text-4xl font-semibold">
            Good morning, {user?.name || "User"}
          </h1>
          <p className="text-sm text-muted-foreground">Here's how Next Gen Relocation is performing today.</p>
        </div>
        <button className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
          <Truck className="h-4 w-4" /> New Job
        </button>
      </div>

      {/* KPIs */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
        {kpis.map((k: any) => (
          <GlassCard key={k.label} hover className="p-3.5">
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground">{k.label}</div>
            <div className="mt-1 font-display text-2xl font-semibold text-foreground">{k.value}</div>
            <div className="mt-1 flex items-center gap-1 text-[11px] text-success">
              <ArrowUpRight className="h-3.5 w-3.5" /> {k.delta}
            </div>
          </GlassCard>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Revenue chart */}
        <GlassCard className="p-6 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <div>
              <h2 className="font-display text-xl font-semibold">Revenue vs Expenses</h2>
              <p className="text-xs text-muted-foreground">Last 6 months</p>
            </div>
            {revenueByMonth.length > 0 && (
              <div className="flex items-center gap-2 rounded-full border border-success/30 bg-success/10 px-3 py-1 text-xs text-success">
                <TrendingUp className="h-3.5 w-3.5" /> Live Data
              </div>
            )}
          </div>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={revenueByMonth} margin={{ left: -16, right: 6, top: 6 }}>
                <defs>
                  <linearGradient id="rev" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--color-gold)" stopOpacity={0.5} />
                    <stop offset="100%" stopColor="var(--color-gold)" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="exp" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--color-chart-2)" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="var(--color-chart-2)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="oklch(0.8 0.115 84 / 0.08)" vertical={false} />
                <XAxis dataKey="month" stroke="var(--color-muted-foreground)" fontSize={12} tickLine={false} axisLine={false} />
                <YAxis stroke="var(--color-muted-foreground)" fontSize={11} tickLine={false} axisLine={false} tickFormatter={(v) => `£${v / 1000}k`} />
                <Tooltip
                  contentStyle={{
                    background: "oklch(0.18 0.008 72)",
                    border: "1px solid oklch(0.85 0.1 86 / 0.2)",
                    borderRadius: 12,
                    color: "var(--color-foreground)",
                  }}
                  formatter={(v: number) => `£${v.toLocaleString()}`}
                />
                <Area type="monotone" dataKey="revenue" stroke="var(--color-gold)" strokeWidth={2.5} fill="url(#rev)" />
                <Area type="monotone" dataKey="expenses" stroke="var(--color-chart-2)" strokeWidth={2} fill="url(#exp)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </GlassCard>

        {/* Upcoming */}
        <GlassCard className="p-6">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <CalendarClock className="h-5 w-5 text-gold" />
              <h2 className="font-display text-xl font-semibold">Upcoming</h2>
            </div>
            {jobEvents.length > 0 && (
              <span className="rounded-full bg-gold/10 border border-gold/20 px-2.5 py-0.5 text-[11px] font-medium text-gold">
                {jobEvents.length} Scheduled
              </span>
            )}
          </div>
          <div className="space-y-3">
            {jobEvents.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-8 text-center border border-dashed border-border/60 rounded-xl bg-card/20 p-4">
                <CalendarClock className="h-8 w-8 text-muted-foreground/50 mb-2" />
                <p className="text-sm font-medium text-muted-foreground">No upcoming events scheduled</p>
                <p className="text-xs text-muted-foreground/70 mt-1">Booked surveys and jobs will appear here.</p>
              </div>
            ) : (
              jobEvents.slice(0, 5).map((e) => {
                const meta = eventTypeMeta[e.type as keyof typeof eventTypeMeta] || eventTypeMeta["Job"];
                return (
                  <div key={e.id} className="flex items-start gap-3 rounded-xl border border-border/60 bg-card/40 p-3 hover:bg-card/60 transition-colors">
                    <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${meta.dot}`} />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium">{e.title}</div>
                      <div className="text-xs text-muted-foreground">
                        {e.date} {e.time ? `· ${e.time}` : ""} {e.driver ? `· ${e.driver}` : ""}
                      </div>
                    </div>
                    <span className={`rounded-md border px-2 py-0.5 text-[10px] font-medium ${meta.chip}`}>{e.type || "Job"}</span>
                  </div>
                );
              })
            )}
          </div>
        </GlassCard>
      </div>

      {/* Recent leads */}
      <GlassCard className="p-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-xl font-semibold">Latest Leads</h2>
          <Phone className="h-4 w-4 text-muted-foreground" />
        </div>
        <div className="space-y-2">
          {leads.slice(0, 5).map((l) => (
            <div key={l.id} className="flex items-center gap-4 rounded-xl border border-border/50 bg-card/30 px-4 py-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-sm font-semibold text-gold">
                {l.name.split(" ").map((n) => n[0]).join("")}
              </div>
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium">{l.name}</div>
                <div className="truncate text-xs text-muted-foreground">{l.moveType}</div>
              </div>
              <div className="hidden text-xs text-muted-foreground sm:block">{l.source}</div>
              <div className="hidden font-medium text-foreground md:block">£{l.estValue.toLocaleString()}</div>
              <span className={`text-xs font-medium ${statusMeta[l.status]?.tone || "text-muted-foreground"}`}>{statusMeta[l.status]?.label || l.status}</span>
            </div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
