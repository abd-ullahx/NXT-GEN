import { createContext, useContext, useState, useEffect, type ReactNode } from "react";

const API_BASE = import.meta.env.VITE_API_URL || "/api";

// ── Types ────────────────────────────────────────────────────────────
export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: "admin" | "manager" | "staff" | "driver" | "surveyor";
  permissions?: Record<string, boolean>;
}

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, passwordConfirmation: string, role?: string) => Promise<void>;
  logout: () => Promise<void>;
  switchRole: (role: "admin" | "staff") => void;
  isAuthenticated: boolean;
}

// ── Token storage helpers ────────────────────────────────────────────
const TOKEN_KEY = "crm_token";
const USER_KEY = "crm_user";
const API_HEADERS = { "ngrok-skip-browser-warning": "true" };

export function getStoredToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function getStoredUser(): AuthUser | null {
  if (typeof window === "undefined") return null;
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try { return JSON.parse(raw); } catch { return null; }
}

function storeAuth(token: string, user: AuthUser) {
  if (typeof window === "undefined") return;
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

function clearAuth() {
  if (typeof window === "undefined") return;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

// ── Context ──────────────────────────────────────────────────────────
const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(getStoredUser());
  const [token, setToken] = useState<string | null>(getStoredToken());
  const [loading, setLoading] = useState(true);

  // Verify token on mount
  useEffect(() => {
    const storedToken = getStoredToken();
    if (!storedToken) {
      setLoading(false);
      return;
    }

    fetch(`${API_BASE}/me`, {
      headers: { ...API_HEADERS, Authorization: `Bearer ${storedToken}` },
    })
      .then((res) => {
        if (!res.ok) throw new Error("Invalid token");
        return res.json();
      })
      .then((userData) => {
        if (getStoredToken() === storedToken) {
          setUser(userData);
          setToken(storedToken);
          storeAuth(storedToken, userData);
        }
      })
      .catch(() => {
        // Only clear auth if token hasn't been replaced by a new login
        if (getStoredToken() === storedToken) {
          clearAuth();
          setUser(null);
          setToken(null);
        }
      })
      .finally(() => {
        if (getStoredToken() === storedToken || !getStoredToken()) {
          setLoading(false);
        }
      });
  }, []);

  const login = async (email: string, password: string) => {
    let res: Response;
    try {
      res = await fetch(`${API_BASE}/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...API_HEADERS },
        body: JSON.stringify({ email, password }),
      });
    } catch (err: any) {
      console.error("Login fetch error:", err);
      throw new Error(`Unable to connect to server (${err.message || "Failed to fetch"}). Please refresh the page.`);
    }

    if (!res.ok) {
      let err: any = {};
      try {
        err = await res.json();
      } catch {}
      throw new Error(err.message || err.errors?.email?.[0] || "Invalid email or password");
    }

    const data = await res.json();
    storeAuth(data.token, data.user);
    setToken(data.token);
    setUser(data.user);
    setLoading(false);
  };

  const register = async (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
    role = "staff",
  ) => {
    const res = await fetch(`${API_BASE}/register`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...API_HEADERS },
      body: JSON.stringify({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        role,
      }),
    });

    if (!res.ok) {
      const err = await res.json();
      const firstError =
        err.message ||
        Object.values(err.errors || {}).flat()[0] ||
        "Registration failed";
      throw new Error(firstError as string);
    }

    const data = await res.json();
    storeAuth(data.token, data.user);
    setToken(data.token);
    setUser(data.user);
    setLoading(false);
  };

  const logout = async () => {
    const storedToken = getStoredToken();
    if (storedToken) {
      try {
        await fetch(`${API_BASE}/logout`, {
          method: "POST",
          headers: { ...API_HEADERS, Authorization: `Bearer ${storedToken}` },
        });
      } catch {
        // ignore network errors during logout
      }
    }
    clearAuth();
    setToken(null);
    setUser(null);
  };

  const switchRole = (newRole: "admin" | "staff") => {
    if (!user) return;
    // Only real system admins are authorized to toggle mode preview
    if (user.role !== "admin" && user.role !== "staff") return;
    const updatedUser = { ...user, role: newRole };
    setUser(updatedUser);
    if (token) storeAuth(token, updatedUser);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        loading,
        login,
        register,
        logout,
        switchRole,
        isAuthenticated: !!token && !!user,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}
