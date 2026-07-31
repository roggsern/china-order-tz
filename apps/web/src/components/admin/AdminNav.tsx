"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ArrowRightIcon, GridIcon } from "@/components/home/icons";
import {
  adminCatalogNavItems,
  adminNavItems,
  adminSettingsNavItems,
  type AdminNavItem,
} from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

interface AdminNavProps {
  collapsed?: boolean;
  onNavigate?: () => void;
  className?: string;
}

function NavLink({
  item,
  isActive,
  collapsed,
  onNavigate,
}: {
  item: AdminNavItem;
  isActive: boolean;
  collapsed?: boolean;
  onNavigate?: () => void;
}) {
  const Icon = item.icon;

  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      title={collapsed ? item.label : undefined}
      className={`admin-nav-link ${isActive ? "admin-nav-link-active" : ""} ${
        collapsed ? "admin-nav-link-collapsed" : ""
      }`}
    >
      <Icon className={`h-[18px] w-[18px] shrink-0 ${isActive ? "text-[#c9a227]" : ""}`} />
      <span className={collapsed ? "hidden truncate lg:inline" : "truncate"}>{item.label}</span>
    </Link>
  );
}

function NavSection({
  title,
  items,
  collapsed,
  pathname,
  onNavigate,
}: {
  title: string;
  items: AdminNavItem[];
  collapsed?: boolean;
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <>
      <p
        className={`px-2 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500 ${
          collapsed ? "hidden lg:block" : ""
        }`}
      >
        {title}
      </p>
      <ul className="space-y-0.5">
        {items.map((item) => {
          const isActive = item.exact
            ? pathname === item.href
            : pathname.startsWith(item.href);

          return (
            <li key={item.href}>
              <NavLink
                item={item}
                isActive={isActive}
                collapsed={collapsed}
                onNavigate={onNavigate}
              />
            </li>
          );
        })}
      </ul>
    </>
  );
}

function filterNavItems(items: AdminNavItem[], permissions: string[] | undefined): AdminNavItem[] {
  return items.filter((item) => {
    if (!item.permission) {
      return true;
    }

    return hasAdminPermission(permissions, item.permission);
  });
}

export function AdminNav({ collapsed = false, onNavigate, className = "" }: AdminNavProps) {
  const pathname = usePathname();
  const { permissions } = useAdminPermissions();
  const managementItems = filterNavItems(adminNavItems, permissions);
  const settingsItems = filterNavItems(adminSettingsNavItems, permissions);

  return (
    <nav className={`flex-1 overflow-y-auto p-3 ${className}`}>
      <NavSection
        title="Management"
        items={managementItems}
        collapsed={collapsed}
        pathname={pathname}
        onNavigate={onNavigate}
      />

      <div className={collapsed ? "mt-3" : "mt-5"}>
        <NavSection
          title="Catalog"
          items={adminCatalogNavItems}
          collapsed={collapsed}
          pathname={pathname}
          onNavigate={onNavigate}
        />
      </div>

      {settingsItems.length > 0 ? (
        <div className={collapsed ? "mt-3" : "mt-5"}>
          <NavSection
            title="Settings"
            items={settingsItems}
            collapsed={collapsed}
            pathname={pathname}
            onNavigate={onNavigate}
          />
        </div>
      ) : null}
    </nav>
  );
}

export function AdminNavFooter({
  collapsed,
  onNavigate,
}: {
  collapsed?: boolean;
  onNavigate?: () => void;
}) {
  return (
    <div className="border-t border-zinc-800 p-3">
      <Link
        href="/"
        onClick={onNavigate}
        title={collapsed ? "View storefront" : undefined}
        className={`admin-nav-link text-zinc-500 hover:text-[#c9a227] ${
          collapsed ? "admin-nav-link-collapsed justify-center" : ""
        }`}
      >
        <GridIcon className="h-4 w-4 shrink-0" />
        <span className={collapsed ? "hidden lg:inline" : ""}>View storefront</span>
        {!collapsed ? <ArrowRightIcon className="ml-auto h-3 w-3 shrink-0" /> : null}
      </Link>
    </div>
  );
}
