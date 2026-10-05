import { useEffect, useState } from "react";
import { useConfirm } from "@/contexts/ConfirmContext";
import { X, UserPlus, Shield, Trash2, Check, UserCheck, ShieldAlert, KeyRound } from "lucide-react";
import { fetchTeamMembers, createUser, updateUser, deleteUser } from "@/lib/api";

interface UserManagementDrawerProps {
  isOpen: boolean;
  onClose: () => void;
}

const MODULES = [
  { key: "leads", label: "Leads Module" },
  { key: "surveys", label: "Pre-Move Surveys" },
  { key: "surveyor", label: "Surveyor Field Portal" },
  { key: "clients", label: "Client Directory" },
  { key: "quotations", label: "Quotations" },
  { key: "invoices", label: "Invoices" },
  { key: "calendar", label: "Calendar & Ops" },
  { key: "contacts", label: "Contacts Directory" },
  { key: "finance", label: "Finance & Cashflow" },
  { key: "integrations", label: "Integrations" },
  { key: "outlook", label: "Outlook Email Sync" },
  { key: "settings", label: "Settings" },
];

const DEFAULT_PERMISSIONS: Record<string, boolean> = {
  leads: true,
  surveys: true,
  surveyor: true,
  clients: true,
  quotations: true,
  invoices: true,
  calendar: true,
  contacts: true,
  finance: true,
  integrations: true,
  outlook: true,
  settings: true,
};

export function UserManagementDrawer({ isOpen, onClose }: UserManagementDrawerProps) {
  const confirm = useConfirm();
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [statusMessage, setStatusMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Form State
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    role: "staff" as "admin" | "manager" | "staff" | "driver" | "surveyor",
    customRoleName: "",
    permissions: { ...DEFAULT_PERMISSIONS },
  });

  const loadUsers = async () => {
    setLoading(true);
    try {
      const data = await fetchTeamMembers();
      setUsers(data);
    } catch (err: any) {
      setStatusMessage({ type: "error", text: err.message || "Failed to load users" });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      loadUsers();
    }
  }, [isOpen]);

  const handleStartEdit = (u: any) => {
    setEditingUserId(u.id);
    setShowAddForm(true);
    setFormData({
      name: u.name,
      email: u.email,
      password: "",
      role: u.role,
      customRoleName: u.custom_role_name || "",
      permissions: { ...DEFAULT_PERMISSIONS, ...(u.permissions || {}) },
    });
  };

  const handleResetForm = () => {
    setEditingUserId(null);
    setShowAddForm(false);
    setFormData({
      name: "",
      email: "",
      password: "",
      role: "staff",
      customRoleName: "",
      permissions: { ...DEFAULT_PERMISSIONS },
    });
  };

  const handlePermissionToggle = (key: string) => {
    setFormData((prev) => ({
      ...prev,
      permissions: {
        ...prev.permissions,
        [key]: !prev.permissions[key],
      },
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatusMessage(null);
    setSubmitting(true);

    try {
      if (editingUserId) {
        await updateUser(editingUserId, formData);
        setStatusMessage({ type: "success", text: "User permissions updated successfully!" });
      } else {
        await createUser(formData);
        setStatusMessage({ type: "success", text: "New user created successfully!" });
      }
      handleResetForm();
      loadUsers();
    } catch (err: any) {
      setStatusMessage({ type: "error", text: err.message || "Operation failed" });
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!await confirm("Are you sure you want to delete this user?")) return;
    setStatusMessage(null);
    try {
      await deleteUser(id);
      setStatusMessage({ type: "success", text: "User removed." });
      loadUsers();
    } catch (err: any) {
      setStatusMessage({ type: "error", text: err.message || "Failed to delete user." });
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onClick={onClose} />

      <div className="fixed inset-y-0 right-0 flex max-w-full pl-10">
        <div className="w-screen max-w-xl bg-card border-l border-border shadow-2xl flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-border/60 px-6 py-5">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold">
                <Shield className="h-5 w-5" />
              </div>
              <div>
                <h2 className="font-display text-xl font-semibold">User Access Control</h2>
                <p className="text-xs text-muted-foreground">Admin panel to create users & set module permissions.</p>
              </div>
            </div>
            <button onClick={onClose} className="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground">
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* Body */}
          <div className="flex-1 overflow-y-auto p-6 space-y-6">
            {statusMessage && (
              <div
                className={`flex items-center justify-between rounded-xl px-4 py-3 text-xs ${
                  statusMessage.type === "success" ? "bg-success/15 border border-success/30 text-success" : "bg-destructive/15 border border-destructive/30 text-destructive"
                }`}
              >
                <span>{statusMessage.text}</span>
                <button onClick={() => setStatusMessage(null)}><X className="h-3.5 w-3.5" /></button>
              </div>
            )}

            {!showAddForm ? (
              <>
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">Team Users ({users.length})</h3>
                  <button
                    onClick={() => setShowAddForm(true)}
                    className="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-3.5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]"
                  >
                    <UserPlus className="h-4 w-4" /> Add New User
                  </button>
                </div>

                <div className="space-y-3">
                  {users.map((u) => (
                    <div key={u.id} className="rounded-xl border border-border/60 bg-background/50 p-4 space-y-3">
                      <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3">
                          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-gold/30 to-transparent text-xs font-semibold text-gold">
                            {u.name.slice(0, 2).toUpperCase()}
                          </div>
                          <div>
                            <div className="text-sm font-medium flex items-center gap-2">
                              {u.name}
                              <span
                                className={`rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-widest border ${
                                  u.role === "admin"
                                    ? "bg-rose-500/10 text-rose-500 border-rose-500/20"
                                    : u.role === "manager"
                                      ? "bg-amber-500/10 text-amber-500 border-amber-500/20"
                                      : u.role === "staff"
                                        ? "bg-blue-500/10 text-blue-400 border-blue-500/20"
                                        : "bg-emerald-500/10 text-emerald-500 border-emerald-500/20"
                                }`}
                              >
                                {u.custom_role_name && u.role === "staff" ? u.custom_role_name : u.role}
                              </span>
                            </div>
                            <div className="text-xs text-muted-foreground">{u.email}</div>
                          </div>
                        </div>

                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => handleStartEdit(u)}
                            className="rounded-lg border border-border bg-card px-2.5 py-1 text-xs text-gold hover:border-gold/40"
                          >
                            Edit Access
                          </button>
                          {u.role !== "admin" && (
                            <button
                              onClick={() => handleDelete(u.id)}
                              className="rounded-lg border border-border bg-card p-1.5 text-destructive hover:border-destructive/40"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          )}
                        </div>
                      </div>

                      {/* Permissions tags */}
                      <div className="flex flex-wrap gap-1 pt-1 border-t border-border/40">
                        {MODULES.map((m) => {
                          const allowed = u.role === "admin" || (u.permissions && u.permissions[m.key] !== false);
                          return (
                            <span
                              key={m.key}
                              className={`rounded-md px-2 py-0.5 text-[10px] font-medium transition-all ${
                                allowed ? "bg-success/10 text-success border border-success/20" : "bg-muted/30 text-muted-foreground opacity-40 line-through"
                              }`}
                            >
                              {m.label}
                            </span>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              /* Add/Edit User Form */
              <form onSubmit={handleSubmit} className="space-y-5 animate-in fade-in duration-150">
                <div className="flex items-center justify-between border-b border-border/50 pb-3">
                  <h3 className="font-display text-lg font-semibold">
                    {editingUserId ? "Edit User Access" : "Create New User"}
                  </h3>
                  <button
                    type="button"
                    onClick={handleResetForm}
                    className="text-xs text-muted-foreground hover:text-foreground"
                  >
                    Cancel
                  </button>
                </div>

                <div className="space-y-4">
                  <div>
                    <label className="mb-1 block text-xs text-muted-foreground">Full Name</label>
                    <input
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      placeholder="e.g. David Miller"
                      className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="mb-1 block text-xs text-muted-foreground">Email</label>
                      <input
                        required
                        type="email"
                        value={formData.email}
                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                        placeholder="david@company.co.uk"
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs text-muted-foreground">Role</label>
                      <select
                        value={formData.role}
                        onChange={(e) => setFormData({ ...formData, role: e.target.value as any })}
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50 text-foreground"
                      >
                        <option value="admin" className="bg-card text-foreground">Admin (Full Access)</option>
                        <option value="manager" className="bg-card text-foreground">Manager</option>
                        <option value="staff" className="bg-card text-foreground">Staff Member</option>
                        <option value="surveyor" className="bg-card text-foreground">Surveyor (Property Inspector)</option>
                        <option value="driver" className="bg-card text-foreground">Driver / Crew</option>
                      </select>
                    </div>
                  </div>

                  {formData.role === "staff" && (
                    <div>
                      <label className="mb-1 block text-xs text-muted-foreground">Custom Role Name (Optional)</label>
                      <input
                        type="text"
                        value={formData.customRoleName}
                        onChange={(e) => setFormData({ ...formData, customRoleName: e.target.value })}
                        placeholder="e.g. Sales Associate, Move Coordinator"
                        className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                      />
                    </div>
                  )}

                  <div>
                    <label className="mb-1 block text-xs text-muted-foreground">
                      {editingUserId ? "New Password (leave blank to keep current)" : "Password"}
                    </label>
                    <input
                      type="password"
                      required={!editingUserId}
                      value={formData.password}
                      onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                      placeholder="Min 8 characters"
                      className="w-full rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50"
                    />
                  </div>

                  {/* Granular Module Access Control (Shown only for Staff / Manager roles) */}
                  {(formData.role === "staff" || formData.role === "manager") ? (
                    <div className="pt-2">
                      <label className="mb-2 block text-xs font-semibold uppercase tracking-wider text-gold">
                        Module Access Permissions
                      </label>
                      <div className="space-y-2 rounded-xl border border-border bg-input/20 p-3">
                        {MODULES.map((m) => (
                          <label key={m.key} className="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-card/60 cursor-pointer">
                            <span className="text-sm font-medium">{m.label}</span>
                            <input
                              type="checkbox"
                              checked={formData.permissions[m.key] !== false}
                              onChange={() => handlePermissionToggle(m.key)}
                              className="h-4 w-4 rounded accent-[oklch(0.8_0.115_84)]"
                            />
                          </label>
                        ))}
                      </div>
                    </div>
                  ) : (
                    <div className="pt-2 rounded-xl border border-gold/30 bg-gold/10 p-3 text-xs">
                      <span className="font-bold text-gold block uppercase tracking-wider mb-1">
                        Fixed Role Access Preset
                      </span>
                      {formData.role === "admin" && (
                        <span className="text-muted-foreground">Admins automatically have full access to all system modules.</span>
                      )}
                      {formData.role === "surveyor" && (
                        <span className="text-muted-foreground">Surveyors automatically land on the dedicated <strong>Surveyor Field Portal</strong> to inspect assigned client properties and upload photos/videos.</span>
                      )}
                      {formData.role === "driver" && (
                        <span className="text-muted-foreground">Drivers automatically land on the dedicated <strong>Jobs & Calendar Ops</strong> section to view assigned jobs.</span>
                      )}
                    </div>
                  )}
                </div>

                <div className="flex justify-end gap-3 pt-3">
                  <button
                    type="button"
                    onClick={handleResetForm}
                    className="rounded-xl border border-border px-4 py-2 text-xs text-muted-foreground hover:bg-muted"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2 text-xs font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)] disabled:opacity-50 flex items-center gap-2"
                  >
                    {submitting && <span className="h-3 w-3 rounded-full border-2 border-primary-foreground border-t-transparent animate-spin" />}
                    {editingUserId ? (submitting ? "Saving..." : "Save Permissions") : (submitting ? "Creating User..." : "Create User Account")}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
