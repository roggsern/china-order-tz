"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { usePathname, useRouter } from "next/navigation";
import type { AdminSession } from "@/lib/admin/session";
import {
  authenticateAdmin,
  getAdminSession,
  isAdminAuthenticated,
  signOutAdmin,
} from "@/lib/admin/session";

type AdminAuthContextValue = {
  isAuthenticated: boolean;
  isReady: boolean;
  session: AdminSession | null;
  signIn: (email: string, password: string) => boolean;
  signOut: () => void;
};

const AdminAuthContext = createContext<AdminAuthContextValue | null>(null);

function AdminAuthLoading({ message }: { message?: string }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-zinc-950 px-4">
      <div className="h-10 w-10 animate-spin rounded-full border-2 border-[#c9a227] border-t-transparent" />
      {message ? <p className="text-sm text-zinc-400">{message}</p> : null}
    </div>
  );
}

export function AdminAuthProvider({ children }: { children: ReactNode }) {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [session, setSession] = useState<AdminSession | null>(null);
  const [isReady, setIsReady] = useState(false);
  const pathname = usePathname();
  const router = useRouter();
  const isLoginPage = pathname === "/admin/login";

  useEffect(() => {
    const existingSession = getAdminSession();
    setSession(existingSession);
    setIsAuthenticated(isAdminAuthenticated());
    setIsReady(true);
  }, []);

  useEffect(() => {
    if (!isReady) {
      return;
    }

    if (!isAuthenticated && !isLoginPage) {
      router.replace("/admin/login");
      return;
    }

    if (isAuthenticated && isLoginPage) {
      router.replace("/admin");
    }
  }, [isAuthenticated, isLoginPage, isReady, router]);

  const signIn = useCallback(
    (email: string, password: string) => {
      const ok = authenticateAdmin(email, password);
      if (ok) {
        const nextSession = getAdminSession();
        setSession(nextSession);
        setIsAuthenticated(true);
        router.replace("/admin");
      }
      return ok;
    },
    [router],
  );

  const signOut = useCallback(() => {
    signOutAdmin();
    setSession(null);
    setIsAuthenticated(false);
    router.replace("/admin/login");
  }, [router]);

  const value = useMemo<AdminAuthContextValue>(
    () => ({ isAuthenticated, isReady, session, signIn, signOut }),
    [isAuthenticated, isReady, session, signIn, signOut],
  );

  if (!isReady) {
    return <AdminAuthLoading />;
  }

  if (!isAuthenticated && !isLoginPage) {
    return <AdminAuthLoading message="Redirecting to admin sign in…" />;
  }

  return <AdminAuthContext.Provider value={value}>{children}</AdminAuthContext.Provider>;
}

export function useAdminAuth() {
  const ctx = useContext(AdminAuthContext);
  if (!ctx) {
    throw new Error("useAdminAuth must be used within AdminAuthProvider");
  }
  return ctx;
}
