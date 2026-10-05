import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Settings as SettingsIcon, Building2, Users, Bell, Shield, Smartphone, Monitor, Apple, Check } from "lucide-react";
import { GlassCard } from "@/components/GlassCard";
import { Logo } from "@/components/Logo";
import { fetchSettings, saveSettings, fetchTeamMembers, updatePassword } from "@/lib/api";

export const Route = createFileRoute("/app/settings")({
  head: () => ({ meta: [{ title: "Settings — Next Gen Relocation CRM" }] }),
  component: SettingsPage,
});

function SettingsPage() {
  const [profile, setProfile] = useState({
    businessName: "NEXT GEN RELOCATION LTD",
    tradingRegion: "Slough & Home Counties",
    contactEmail: "hello@nextgenrelocation.co.uk",
    phone: "+44 1753 555 200",
  });
  const [team, setTeam] = useState<{ id: number; name: string; email: string; role: string }[]>([]);
  const [saved, setSaved] = useState(false);

  // Password state
  const [passData, setPassData] = useState({ current_password: "", new_password: "", confirm_password: "" });
  const [passLoading, setPassLoading] = useState(false);
  const [passMessage, setPassMessage] = useState("");

  useEffect(() => {
    fetchSettings()
      .then((data) => setProfile(data))
      .catch((err) => console.warn("Failed to fetch settings from MySQL API:", err));

    fetchTeamMembers()
      .then((data) => setTeam(data))
      .catch((err) => console.warn("Failed to fetch team members from API:", err));
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await saveSettings(profile);
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      console.error("Error saving settings:", err);
    }
  };

  const handlePasswordChange = async (e: React.FormEvent) => {
    e.preventDefault();
    setPassMessage("");

    if (passData.new_password !== passData.confirm_password) {
      setPassMessage("New passwords do not match.");
      return;
    }
    if (passData.new_password.length < 8) {
      setPassMessage("New password must be at least 8 characters.");
      return;
    }

    setPassLoading(true);
    try {
      await updatePassword({
        current_password: passData.current_password,
        new_password: passData.new_password,
      });
      setPassMessage("Password updated successfully!");
      setPassData({ current_password: "", new_password: "", confirm_password: "" });
    } catch (err: any) {
      setPassMessage(err.message || "Failed to update password.");
    } finally {
      setPassLoading(false);
    }
  };

  const apps = [
    { icon: Monitor, title: "Web CRM", desc: "Full admin & staff workspace (this app)." },
    { icon: Smartphone, title: "Customer App", desc: "Track jobs, sign documents, pay deposits." },
    { icon: Smartphone, title: "Driver App", desc: "Daily jobs, routes & live GPS tracking." },
    { icon: Apple, title: "Mac & Windows", desc: "Native desktop apps for the office team." },
  ];

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
          <SettingsIcon className="h-3.5 w-3.5" /> Configuration
        </div>
        <h1 className="mt-1 font-display text-4xl font-semibold">Settings</h1>
        <p className="text-sm text-muted-foreground">Manage your company profile and settings in MySQL.</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <GlassCard className="p-6 lg:col-span-2">
          <div className="mb-5 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Building2 className="h-5 w-5 text-gold" />
              <h2 className="font-display text-xl font-semibold">Business Profile</h2>
            </div>
            {saved && (
              <span className="flex items-center gap-1 text-xs text-success bg-success/15 px-3 py-1 rounded-full border border-success/30">
                <Check className="h-3.5 w-3.5" /> Saved to MySQL
              </span>
            )}
          </div>
          <div className="mb-6 flex items-center gap-4">
            <Logo size={56} showText={false} />
            <div>
              <div className="font-medium">{profile.businessName}</div>
              <div className="text-xs text-muted-foreground">{profile.tradingRegion} · Premium Removals</div>
            </div>
          </div>
          <form onSubmit={handleSave}>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Friendly Business Name</label>
                <input
                  value={profile.businessName}
                  onChange={(e) => setProfile({ ...profile, businessName: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Trading Region</label>
                <input
                  value={profile.tradingRegion}
                  onChange={(e) => setProfile({ ...profile, tradingRegion: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Contact Email</label>
                <input
                  value={profile.contactEmail}
                  onChange={(e) => setProfile({ ...profile, contactEmail: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs text-muted-foreground">Phone</label>
                <input
                  value={profile.phone}
                  onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50"
                />
              </div>
            </div>
            <button
              type="submit"
              className="mt-5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
            >
              Save changes to MySQL
            </button>
          </form>
        </GlassCard>

        <div className="space-y-6">
          <GlassCard className="p-6">
            <div className="mb-4 flex items-center gap-2">
              <Shield className="h-5 w-5 text-gold" />
              <h2 className="font-display text-xl font-semibold">Change Password</h2>
            </div>
            {passMessage && (
              <div className={`mb-3 text-xs p-2.5 rounded-lg border ${passMessage.includes('success') ? 'bg-success/15 border-success/30 text-success' : 'bg-destructive/15 border-destructive/30 text-destructive'}`}>
                {passMessage}
              </div>
            )}
            <form onSubmit={handlePasswordChange} className="space-y-3">
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Current Password</label>
                <input
                  type="password"
                  required
                  value={passData.current_password}
                  onChange={(e) => setPassData({ ...passData, current_password: e.target.value })}
                  placeholder="Current password"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">New Password</label>
                <input
                  type="password"
                  required
                  value={passData.new_password}
                  onChange={(e) => setPassData({ ...passData, new_password: e.target.value })}
                  placeholder="Min 8 characters"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-muted-foreground">Confirm New Password</label>
                <input
                  type="password"
                  required
                  value={passData.confirm_password}
                  onChange={(e) => setPassData({ ...passData, confirm_password: e.target.value })}
                  placeholder="Re-enter new password"
                  className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                />
              </div>
              <button
                type="submit"
                disabled={passLoading}
                className="w-full rounded-xl bg-gold/20 py-2 text-xs font-semibold text-gold border border-gold/30 hover:bg-gold/30 transition-all disabled:opacity-60"
              >
                {passLoading ? "Updating Password..." : "Update Password"}
              </button>
            </form>
          </GlassCard>
        </div>
      </div>

      <GlassCard className="p-6">
        <div className="mb-4 flex items-center gap-2">
          <Users className="h-5 w-5 text-gold" />
          <h2 className="font-display text-xl font-semibold">Team Members</h2>
        </div>
        <div className="divide-y divide-border/40">
          {team.map((m) => (
            <div key={m.email} className="flex items-center gap-4 py-3">
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-xs font-semibold text-gold">
                {m.name.split(" ").map((n) => n[0]).join("")}
              </div>
              <div className="min-w-0 flex-1">
                <div className="text-sm font-medium">{m.name}</div>
                <div className="text-xs text-muted-foreground">{m.email}</div>
              </div>
              <span className="rounded-md border border-border bg-card/40 px-2 py-0.5 text-xs text-muted-foreground">{m.role}</span>
            </div>
          ))}
        </div>
      </GlassCard>

      <GlassCard className="p-6">
        <h2 className="mb-1 font-display text-xl font-semibold">Platform Apps</h2>
        <p className="mb-4 text-sm text-muted-foreground">One ecosystem — admin, customer, driver and desktop.</p>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {apps.map((a) => (
            <div key={a.title} className="rounded-xl border border-border/60 bg-card/40 p-4">
              <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold">
                <a.icon className="h-5 w-5" />
              </div>
              <div className="text-sm font-medium">{a.title}</div>
              <p className="mt-1 text-xs text-muted-foreground">{a.desc}</p>
              <span className="mt-3 inline-block rounded-full border border-gold/30 px-2 py-0.5 text-[10px] text-gold">Coming next</span>
            </div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
