"use client";

import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { ChevronLeftIcon } from "@/components/home/icons";
import { AdminNav, AdminNavFooter } from "@/components/admin/AdminNav";

interface AdminSidebarProps {
  collapsed?: boolean;
  onToggleCollapse?: () => void;
}

export function AdminSidebar({ collapsed = false, onToggleCollapse }: AdminSidebarProps) {
  return (
    <aside
      className={`admin-sidebar hidden shrink-0 flex-col border-r border-zinc-800 bg-zinc-950 md:flex ${
        collapsed ? "md:w-[4.5rem] lg:w-64" : "w-64"
      }`}
    >
      <div className="flex items-center justify-between gap-2 border-b border-zinc-800 px-3 py-4">
        <div className={`flex min-w-0 items-center gap-2.5 ${collapsed ? "justify-center" : ""}`}>
          <HorizontalBrandLogo size="sm" href="/admin" className={collapsed ? "scale-90" : ""} />
          {!collapsed ? (
            <span className="rounded-md bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#c9a227]">
              Admin
            </span>
          ) : (
            <span className="hidden rounded-md bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#c9a227] lg:inline">
              Admin
            </span>
          )}
        </div>
        {onToggleCollapse ? (
          <button
            type="button"
            onClick={onToggleCollapse}
            className="admin-touch-target hidden rounded-lg border border-zinc-800 p-2 text-zinc-400 transition hover:border-[#c9a227]/30 hover:text-[#c9a227] md:inline-flex lg:hidden"
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            aria-expanded={!collapsed}
          >
            {collapsed ? (
              <ChevronLeftIcon className="h-4 w-4 rotate-180" />
            ) : (
              <ChevronLeftIcon className="h-4 w-4" />
            )}
          </button>
        ) : null}
      </div>

      <AdminNav collapsed={collapsed} />
      <AdminNavFooter collapsed={collapsed} />
    </aside>
  );
}
