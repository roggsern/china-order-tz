"use client";

import { SearchIcon, UserIcon } from "@/components/home/icons";

interface AdminHeaderProps {
  title?: string;
}

export function AdminHeader({ title = "Admin" }: AdminHeaderProps) {
  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-4 border-b border-zinc-200 bg-white px-4 sm:px-6">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <span className="hidden text-sm font-semibold text-zinc-900 sm:block">{title}</span>
      </div>

      <div className="relative hidden max-w-xs flex-1 sm:block">
        <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
        <input
          type="search"
          placeholder="Search admin..."
          className="admin-input w-full py-2 pl-9 pr-3"
          aria-label="Search admin"
        />
      </div>

      <div className="flex items-center gap-2">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-[#c9a227] to-[#8b6914] text-xs font-bold text-white">
          A
        </div>
        <div className="hidden sm:block">
          <p className="text-xs font-medium text-zinc-900">Admin User</p>
          <p className="text-[10px] text-zinc-500">admin@chinaorder.tz</p>
        </div>
        <UserIcon className="h-5 w-5 text-zinc-400 sm:hidden" />
      </div>
    </header>
  );
}
