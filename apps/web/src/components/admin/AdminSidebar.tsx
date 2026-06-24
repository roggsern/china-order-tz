"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  HomeIcon,
  PackageIcon,
  GridIcon,
  ArrowRightIcon,
} from "@/components/home/icons";

const navItems = [
  { label: "Dashboard", href: "/admin", icon: HomeIcon, exact: true },
  { label: "Products", href: "/admin/products", icon: PackageIcon, exact: false },
];

export function AdminSidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex w-full flex-col border-r border-zinc-200 bg-[#f6f6f7] lg:w-60 lg:shrink-0">
      <div className="border-b border-zinc-200 bg-white px-4 py-4">
        <Link href="/admin" className="flex items-center gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-[#c9a227] to-[#8b6914] text-xs font-black text-white shadow-sm">
            C
          </span>
          <div className="leading-tight">
            <span className="block text-[13px] font-semibold text-zinc-900">
              CHINA ORDER <span className="text-[#c9a227]">TZ</span>
            </span>
            <span className="block text-[11px] text-zinc-500">Admin</span>
          </div>
        </Link>
      </div>

      <nav className="flex-1 p-3">
        <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
          Store
        </p>
        <ul className="space-y-0.5">
          {navItems.map((item) => {
            const isActive = item.exact
              ? pathname === item.href
              : pathname.startsWith(item.href);
            const Icon = item.icon;
            return (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className={`flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition ${
                    isActive
                      ? "bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200"
                      : "text-zinc-600 hover:bg-white/60 hover:text-zinc-900"
                  }`}
                >
                  <Icon className={`h-[18px] w-[18px] ${isActive ? "text-[#8b6914]" : ""}`} />
                  {item.label}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      <div className="border-t border-zinc-200 p-3">
        <Link
          href="/"
          className="flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-zinc-500 transition hover:bg-white hover:text-zinc-800"
        >
          <GridIcon className="h-4 w-4" />
          View storefront
          <ArrowRightIcon className="ml-auto h-3 w-3" />
        </Link>
      </div>
    </aside>
  );
}
