"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import {
  HomeIcon,
  PackageIcon,
  GridIcon,
  ArrowRightIcon,
  DocumentIcon,
  TagIcon,
  ChartBarIcon,
  UserIcon,
  SettingsIcon,
} from "@/components/home/icons";

const navItems = [
  { label: "Dashboard", href: "/admin", icon: HomeIcon, exact: true },
  { label: "Analytics", href: "/admin/analytics", icon: ChartBarIcon, exact: false },
  { label: "Orders", href: "/admin/orders", icon: DocumentIcon, exact: false },
  { label: "Products", href: "/admin/products", icon: PackageIcon, exact: false },
  { label: "Categories", href: "/admin/categories", icon: TagIcon, exact: false },
  { label: "Customers", href: "/admin/customers", icon: UserIcon, exact: false },
  { label: "Settings", href: "/admin/settings", icon: SettingsIcon, exact: false },
];

export function AdminSidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex w-full flex-col border-r border-zinc-800 bg-zinc-950 lg:w-64 lg:shrink-0">
      <div className="border-b border-zinc-800 px-4 py-5">
        <div className="flex items-center gap-2.5">
          <HorizontalBrandLogo size="sm" />
          <span className="rounded-md bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#c9a227]">
            Admin
          </span>
        </div>
      </div>

      <nav className="flex-1 p-3">
        <p className="px-2 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">
          Management
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
                  className={`flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-[13px] font-medium transition ${
                    isActive
                      ? "bg-gradient-to-r from-[#c9a227]/20 to-transparent text-[#e8c547] ring-1 ring-[#c9a227]/30"
                      : "text-zinc-400 hover:bg-zinc-900 hover:text-white"
                  }`}
                >
                  <Icon className={`h-[18px] w-[18px] ${isActive ? "text-[#c9a227]" : ""}`} />
                  {item.label}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      <div className="border-t border-zinc-800 p-3">
        <Link
          href="/"
          className="flex items-center gap-2 rounded-lg px-3 py-2.5 text-[13px] text-zinc-500 transition hover:bg-zinc-900 hover:text-[#c9a227]"
        >
          <GridIcon className="h-4 w-4" />
          View storefront
          <ArrowRightIcon className="ml-auto h-3 w-3" />
        </Link>
      </div>
    </aside>
  );
}
