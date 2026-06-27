"use client";

import { SearchIcon } from "@/components/home/icons";
import { useAdminAuth } from "@/components/admin/AdminAuthProvider";

interface AdminHeaderProps {
  title?: string;
}

export function AdminHeader({ title = "Admin Dashboard" }: AdminHeaderProps) {
  const { signOut } = useAdminAuth();

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-4 border-b border-zinc-800 bg-zinc-950 px-4 sm:px-6">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <span className="text-sm font-semibold text-white">{title}</span>
        <span className="hidden rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#c9a227] sm:inline">
          Secure admin
        </span>
      </div>

      <div className="relative hidden max-w-xs flex-1 md:block">
        <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
        <input
          type="search"
          placeholder="Search admin..."
          className="w-full rounded-lg border border-zinc-700 bg-zinc-900 py-2 pl-9 pr-3 text-sm text-white placeholder:text-zinc-500 outline-none focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
          aria-label="Search admin"
        />
      </div>

      <div className="flex items-center gap-3">
        <div className="hidden text-right sm:block">
          <p className="text-xs font-medium text-white">Store Admin</p>
          <p className="text-[10px] text-zinc-500">Orders & catalog</p>
        </div>
        <button
          type="button"
          onClick={signOut}
          className="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-zinc-300 transition hover:border-[#c9a227]/40 hover:text-[#c9a227]"
        >
          Sign out
        </button>
      </div>
    </header>
  );
}
