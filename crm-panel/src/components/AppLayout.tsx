import { Link, useNavigate, useRouterState, useSearch } from "@tanstack/react-router";
import {
  LayoutDashboard,
  Users,
  CalendarDays,
  Contact2,
  Wallet,
  Plug,
  Mail,
  Settings,
  Bell,
  Search,
  LogOut,
  Truck,
  Menu,
  X,
  User,
  Shield,
  FileText,
  Receipt,
  ClipboardCheck,
  UserCheck,
  Camera,
  MessageSquare,
  CheckCircle2,
} from "lucide-react";
import { useState, type ReactNode } from "react";
import { Logo } from "@/components/Logo";
import { cn } from "@/lib/utils";
import { useAuth } from "@/lib/auth";
import { UserManagementDrawer } from "@/components/UserManagementDrawer";
import { IncomingCallBanner } from "@/components/IncomingCallBanner";
import { IncomingChatCallBanner } from "@/components/IncomingChatCallBanner";
import { NotificationsMenu } from "@/components/NotificationsMenu";

const nav = [
  { to: "/app/dashboard", label: "Dashboard", icon: LayoutDashboard, moduleKey: "dashboard" },
  { to: "/app/leads", label: "Leads", icon: Users, moduleKey: "leads" },
  { to: "/app/surveys", label: "Surveys", icon: ClipboardCheck, moduleKey: "surveys" },
  { to: "/app/surveyor", label: "Surveyor Portal", icon: Camera, moduleKey: "surveyor" },
  { to: "/app/clients", label: "Clients", icon: UserCheck, moduleKey: "clients" },
  { to: "/app/quotations", label: "Quotations", icon: FileText, moduleKey: "quotations" },
  { to: "/app/jobs", label: "Jobs", icon: Truck, moduleKey: "jobs" },
  { to: "/app/invoices", label: "Invoices", icon: Receipt, moduleKey: "invoices" },
  { to: "/app/calendar", label: "Calendar", icon: CalendarDays, moduleKey: "calendar" },
  { to: "/app/contacts", label: "Contacts", icon: Contact2, moduleKey: "contacts" },
  { to: "/app/finance", label: "Finance", icon: Wallet, moduleKey: "finance" },
  { to: "/app/integrations", label: "Integrations", icon: Plug, moduleKey: "integrations" },
  { to: "/app/outlook", label: "Outlook", icon: Mail, moduleKey: "outlook" },
  { to: "/app/chat", label: "Team Chat", icon: MessageSquare, moduleKey: "chat" },
  { to: "/app/settings", label: "Settings", icon: Settings, moduleKey: "settings" },
] as const;

import { useIsFetching, useIsMutating } from "@tanstack/react-query";

export function AppLayout({ children }: { children: ReactNode }) {
  const isFetching = useIsFetching();
  const isMutating = useIsMutating();
  const isLoadingActive = isFetching > 0 || isMutating > 0;

  const navigate = useNavigate();
  const { user, logout, switchRole } = useAuth();
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const searchParams = useSearch({ strict: false });
  const [open, setOpen] = useState(false);
  const [isUserDrawerOpen, setIsUserDrawerOpen] = useState(false);

  const handleSignOut = async () => {
    await logout();
    navigate({ to: "/" });
  };

  // Filter navigation links based on role presets or staff granular permissions
  const filteredNav = nav.filter((item) => {
    if (user?.role === "admin") return true;

    // Fixed role presets
    if (user?.role === "surveyor") {
      return item.moduleKey === "surveyor" || item.moduleKey === "calendar" || item.moduleKey === "chat";
    }
    if (user?.role === "driver") {
      return item.moduleKey === "jobs" || item.moduleKey === "calendar" || item.moduleKey === "dashboard";
    }

    // Staff / Manager custom module permissions
    if (item.moduleKey === "dashboard") return true;
    if (!user?.permissions) return true;
    return user.permissions[item.moduleKey] !== false;
  });

  const SidebarBody = (
    <>
      <div className="px-5 pb-6 pt-6">
        <Logo />
      </div>
      <nav className="flex-1 space-y-1 px-3">
        {(filteredNav as any[]).flatMap((item: any) => {
          if (item.moduleKey === "surveyor" && user?.role === "surveyor") {
            return [
              { to: "/app/surveyor", search: { tab: "dashboard" }, label: "Dashboard", icon: LayoutDashboard },
              { to: "/app/surveyor", search: { tab: "pending" }, label: "Assigned Surveys", icon: ClipboardCheck },
              { to: "/app/surveyor", search: { tab: "completed" }, label: "Completed Surveys", icon: CheckCircle2 },
            ];
          }
          return [item];
        }).map((item: any, index: number) => {
          // Check active state
          let active = pathname.startsWith(item.to);
          
          // If it has search params, strictly check if they match
          if (active && (item as any).search) {
            const search = (item as any).search;
            if (search.tab && searchParams.tab !== search.tab) active = false;
            if (search.view && searchParams.view !== search.view) active = false;
            if (!search.view && searchParams.view) active = false;
          }

          return (
            <Link
              key={`${item.to}-${index}`}
              to={item.to}
              search={(item as any).search}
              onClick={() => setOpen(false)}
              className={cn(
                "group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-all",
                active
                  ? "bg-gradient-to-r from-gold/20 to-gold/5 text-foreground gold-ring"
                  : "text-muted-foreground hover:bg-sidebar-accent hover:text-foreground",
              )}
            >
              <item.icon className={cn("h-[18px] w-[18px]", active ? "text-gold" : "text-muted-foreground group-hover:text-gold")} />
              {item.label}
            </Link>
          );
        })}
      </nav>

      {/* Admin User Management Button in Sidebar */}
      {user?.role === "admin" && (
        <button
          onClick={() => setIsUserDrawerOpen(true)}
          className="mx-3 mb-2 flex items-center gap-3 rounded-xl border border-gold/30 bg-gold/10 px-3 py-2.5 text-xs font-semibold text-gold transition-colors hover:bg-gold/20"
        >
          <Shield className="h-4 w-4" /> User Access Control
        </button>
      )}

      <div className="mx-3 mb-4 rounded-xl border border-gold/15 bg-success/5 px-4 py-3">
        <div className="flex items-center gap-2 text-xs">
          <span className="h-2 w-2 animate-pulse rounded-full bg-success" />
          <span className="text-muted-foreground">All systems operational</span>
        </div>
      </div>
      <button
        onClick={handleSignOut}
        className="mx-3 mb-5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-destructive"
      >
        <LogOut className="h-[18px] w-[18px]" />
        Sign out
      </button>
    </>
  );

  return (
    <div className="min-h-screen lg:flex">
      {/* Desktop sidebar */}
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col overflow-y-auto border-r border-sidebar-border bg-sidebar lg:flex [scrollbar-width:thin] [scrollbar-color:rgba(212,175,55,0.2)_transparent]">
        {SidebarBody}
      </aside>

      {/* Mobile drawer */}
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setOpen(false)} />
          <aside className="absolute left-0 top-0 flex h-full w-64 flex-col overflow-y-auto border-r border-sidebar-border bg-sidebar [scrollbar-width:thin] [scrollbar-color:rgba(212,175,55,0.2)_transparent]">
            <button onClick={() => setOpen(false)} className="absolute right-3 top-4 text-muted-foreground z-10">
              <X className="h-5 w-5" />
            </button>
            {SidebarBody}
          </aside>
        </div>
      )}

      {/* User Management Side Panel Drawer */}
      <UserManagementDrawer
        isOpen={isUserDrawerOpen}
        onClose={() => setIsUserDrawerOpen(false)}
      />

      <div className="flex min-w-0 flex-1 flex-col relative">
        {/* Global Loading Progress Bar for Instant System Feedback */}
        {isLoadingActive && (
          <div className="fixed top-0 left-0 right-0 z-50 h-1 overflow-hidden bg-gold/10">
            <div className="h-full w-full bg-gradient-to-r from-gold via-amber-400 to-gold animate-pulse origin-left" />
          </div>
        )}

        {/* Top bar */}
        <header className="sticky top-0 z-40 flex items-center gap-3 border-b border-border/60 bg-background px-4 py-3 lg:px-8">
          <button onClick={() => setOpen(true)} className="lg:hidden">
            <Menu className="h-5 w-5 text-foreground" />
          </button>



          <div className="relative hidden flex-1 max-w-md md:block">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              placeholder="Search leads, jobs, contacts…"
              className="w-full rounded-full border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-gold/50"
            />
          </div>
          <div className="ml-auto flex items-center gap-3">
            <IncomingCallBanner />
            <IncomingChatCallBanner />
            <NotificationsMenu />

            {/* Top Right User Profile Section / Drawer Trigger */}
            <div
              onClick={() => user?.role === "admin" && setIsUserDrawerOpen(true)}
              className={`flex items-center gap-3 rounded-full border border-border bg-card/60 py-1 pl-1 pr-4 transition-colors ${
                user?.role === "admin" ? "cursor-pointer hover:border-gold/40" : ""
              }`}
              title={user?.role === "admin" ? "Click to manage team users & access permissions" : ""}
            >
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-gold to-gold-dim text-primary-foreground text-xs font-semibold">
                {user?.name ? user.name.slice(0, 2).toUpperCase() : <User className="h-4 w-4" />}
              </div>
              <div className="hidden text-left leading-tight sm:block">
                <div className="text-xs font-medium text-foreground flex items-center gap-1">
                  {user?.name || "User"}
                  {user?.role === "admin" && <Shield className="h-3 w-3 text-gold" />}
                </div>
                <div className="text-[10px] text-muted-foreground capitalize">{user?.role || "Staff"}</div>
              </div>
            </div>
          </div>
        </header>

        <main className="flex-1 px-4 py-6 lg:px-8 lg:py-8">{children}</main>
      </div>
    </div>
  );
}
