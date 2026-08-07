"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { AdminSidebar } from "@/components/admin/AdminSidebar";
import { AdminHeader } from "@/components/admin/AdminHeader";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { CloseIcon } from "@/components/home/icons";
import { AdminNav, AdminNavFooter } from "@/components/admin/AdminNav";

export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const isLoginPage = pathname === "/admin/login";
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  useEffect(() => {
    setMobileNavOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!mobileNavOpen) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMobileNavOpen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [mobileNavOpen]);

  if (isLoginPage) {
    return <>{children}</>;
  }

  return (
    <div className="flex min-h-screen bg-[var(--admin-bg,#f3f4f6)] lg:flex-row">
      <AdminSidebar
        collapsed={sidebarCollapsed}
        onToggleCollapse={() => setSidebarCollapsed((current) => !current)}
      />

      <div className="flex min-h-screen min-w-0 flex-1 flex-col">
        <AdminHeader
          onOpenMobileNav={() => setMobileNavOpen(true)}
          mobileNavOpen={mobileNavOpen}
        />
        <main className="flex-1 overflow-x-hidden overflow-y-auto">{children}</main>
      </div>

      {mobileNavOpen ? (
        <div className="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Admin navigation">
          <button
            type="button"
            className="absolute inset-0 bg-black/60 backdrop-blur-[1px]"
            aria-label="Close navigation menu"
            onClick={() => setMobileNavOpen(false)}
          />
          <aside id="admin-mobile-nav" className="admin-mobile-drawer absolute inset-y-0 left-0 flex w-[min(100%,20rem)] flex-col border-r border-zinc-800 bg-zinc-950 shadow-2xl">
            <div className="flex items-center justify-between gap-3 border-b border-zinc-800 px-4 py-4">
              <div className="min-w-0">
                <HorizontalBrandLogo size="sm" href="/admin" />
                <p className="mt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-[#c9a227]">
                  Admin portal
                </p>
              </div>
              <button
                type="button"
                onClick={() => setMobileNavOpen(false)}
                className="admin-touch-target rounded-lg border border-zinc-800 p-2 text-zinc-400 transition hover:text-white"
                aria-label="Close navigation menu"
              >
                <CloseIcon className="h-5 w-5" />
              </button>
            </div>
            <AdminNav onNavigate={() => setMobileNavOpen(false)} />
            <AdminNavFooter onNavigate={() => setMobileNavOpen(false)} />
          </aside>
        </div>
      ) : null}
    </div>
  );
}
