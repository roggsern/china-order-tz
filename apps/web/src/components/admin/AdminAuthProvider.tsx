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
import {
  authenticateAdmin,
  isAdminAuthenticated,
  signOutAdmin,
} from "@/lib/admin/session";

type AdminAuthContextValue = {
  isAuthenticated: boolean;
  isReady: boolean;
  signIn: (pin: string) => boolean;
  signOut: () => void;
};

const AdminAuthContext = createContext<AdminAuthContextValue | null>(null);

export function AdminAuthProvider({ children }: { children: ReactNode }) {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isReady, setIsReady] = useState(false);
  const pathname = usePathname();
  const router = useRouter();
  const isLoginPage = pathname === "/admin/login";

  useEffect(() => {
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

  const signIn = useCallback((pin: string) => {
    const ok = authenticateAdmin(pin);
    if (ok) {
      setIsAuthenticated(true);
    }
    return ok;
  }, []);

  const signOut = useCallback(() => {
    signOutAdmin();
    setIsAuthenticated(false);
    router.replace("/admin/login");
  }, [router]);

  const value = useMemo(
    () => ({ isAuthenticated, isReady, signIn, signOut }),
    [isAuthenticated, isReady, signIn, signOut],
  );

  if (!isReady) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-zinc-950">
        <div className="h-10 w-10 animate-spin rounded-full border-2 border-[#c9a227] border-t-transparent" />
      </div>
    );
  }

  if (!isAuthenticated && !isLoginPage) {
    return null;
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
