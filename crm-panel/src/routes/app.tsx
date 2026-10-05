import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { Loader2 } from "lucide-react";
import { AppLayout } from "@/components/AppLayout";
import { useAuth, getStoredToken } from "@/lib/auth";

export const Route = createFileRoute("/app")({
  component: AppGuard,
});

function AppGuard() {
  const { isAuthenticated, loading } = useAuth();
  const navigate = useNavigate();
  const hasToken = Boolean(getStoredToken());

  useEffect(() => {
    if (!loading && !isAuthenticated && !hasToken) {
      navigate({ to: "/" });
    }
  }, [loading, isAuthenticated, hasToken, navigate]);

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <Loader2 className="h-8 w-8 animate-spin text-gold" />
      </div>
    );
  }

  if (!isAuthenticated && !hasToken) {
    return null;
  }

  return (
    <AppLayout>
      <Outlet />
    </AppLayout>
  );
}
