import { createFileRoute, useNavigate, Link } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { ShieldCheck, Lock, Mail, ArrowRight, Sparkles, Loader2, AlertCircle } from "lucide-react";
import { Logo } from "@/components/Logo";
import { useAuth } from "@/lib/auth";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Sign in — Next Gen Relocation CRM" },
      { name: "description", content: "Secure admin and staff access to the Next Gen Relocation luxury removals CRM." },
    ],
  }),
  component: Login,
});

function Login() {
  const navigate = useNavigate();
  const { login, isAuthenticated } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  // Recent login emails list from localStorage with defaults
  const [recentEmails, setRecentEmails] = useState<string[]>(() => {
    try {
      const stored = localStorage.getItem("recent_login_emails");
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {}
    return ["admin@nextgenrelocation.co.uk", "staff@nextgenrelocation.co.uk"];
  });

  // If already authenticated, redirect to dashboard
  useEffect(() => {
    if (isAuthenticated) {
      navigate({ to: "/app/dashboard" });
    }
  }, [isAuthenticated, navigate]);

  if (isAuthenticated) {
    return null;
  }

  const saveRecentEmail = (emailToSave: string) => {
    if (!emailToSave) return;
    const updated = [emailToSave, ...recentEmails.filter((e) => e !== emailToSave)].slice(0, 5);
    setRecentEmails(updated);
    try {
      localStorage.setItem("recent_login_emails", JSON.stringify(updated));
    } catch {}
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSubmitting(true);
    try {
      await login(email, password);
      saveRecentEmail(email);
      window.location.href = "/crm/app/dashboard";
    } catch (err: any) {
      setError(err.message || "Login failed. Please check your credentials.");
      setSubmitting(false);
    }
  };

  return (
    <div className="relative min-h-screen overflow-hidden">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -left-40 top-0 h-[420px] w-[420px] rounded-full bg-gold/10 blur-[120px]" />
        <div className="absolute bottom-0 right-0 h-[460px] w-[460px] rounded-full bg-gold/5 blur-[140px]" />
      </div>

      <div className="relative grid min-h-screen lg:grid-cols-2">
        {/* Brand panel */}
        <div className="relative hidden flex-col justify-between overflow-hidden border-r border-gold/15 p-12 lg:flex">
          <Logo size={48} />
          <div className="relative">
            <h1 className="font-display text-5xl font-semibold leading-tight">
              <span className="gold-text">Next Gen</span>, Next Home,
              <br />
              Next Chapter.
            </h1>
            <p className="mt-5 max-w-md text-muted-foreground">
              The world's most refined CRM for premium removal companies — manage leads, surveys, jobs, drivers, fleet,
              calls, finance and AI automations in one ultra-premium platform.
            </p>
          </div>
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <ShieldCheck className="h-4 w-4 text-gold" />
            Enterprise-grade security · GDPR compliant
          </div>
        </div>

        {/* Login form */}
        <div className="flex items-center justify-center p-6 lg:p-12">
          <div className="glass-card w-full max-w-md rounded-3xl p-8 relative">
            <div className="mb-8 lg:hidden">
              <Logo size={44} />
            </div>
            <div className="mb-1 flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
              <Sparkles className="h-3.5 w-3.5" /> Secure access
            </div>
            <h2 className="font-display text-3xl font-semibold">Welcome back</h2>
            <p className="mt-1 text-sm text-muted-foreground">Sign in to your Next Gen workspace.</p>

            {error && (
              <div className="mt-4 flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <AlertCircle className="h-4 w-4 shrink-0" />
                {error}
              </div>
            )}

            <form className="mt-6 space-y-4" onSubmit={handleSubmit}>
              
              {/* Email Input Field with Native Browser Datalist Suggestions */}
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Email</label>
                <div className="relative">
                  <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input
                    type="email"
                    required
                    list="recent-login-emails"
                    autoComplete="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="you@company.co.uk"
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 pl-10 pr-4 text-sm outline-none transition-colors focus:border-gold/50"
                  />
                </div>
                <datalist id="recent-login-emails">
                  {recentEmails.map((itemEmail) => (
                    <option key={itemEmail} value={itemEmail} />
                  ))}
                </datalist>
              </div>
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Password</label>
                <div className="relative">
                  <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input
                    type="password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Enter your password"
                    className="w-full rounded-xl border border-border bg-input/40 py-2.5 pl-10 pr-4 text-sm outline-none transition-colors focus:border-gold/50"
                  />
                </div>
              </div>
              <div className="flex items-center justify-between text-xs">
                <label className="flex items-center gap-2 text-muted-foreground">
                  <input type="checkbox" defaultChecked className="accent-[oklch(0.8_0.115_84)]" /> Remember me
                </label>
              </div>
              <button
                type="submit"
                disabled={submitting}
                className="group flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim py-3 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)] disabled:opacity-60 cursor-pointer"
              >
                {submitting ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" /> Signing in…
                  </>
                ) : (
                  <>
                    Enter workspace
                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                  </>
                )}
              </button>
            </form>

            <p className="mt-6 text-center text-xs text-muted-foreground">
              Contact your administrator if you need account access.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
