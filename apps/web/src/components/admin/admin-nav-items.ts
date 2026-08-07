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
  StoreIcon,
  LinkIcon,
  StarOutlineIcon,
  EditIcon,
} from "@/components/home/icons";
import type { ComponentType } from "react";

export type AdminNavItem = {
  label: string;
  href: string;
  icon: ComponentType<{ className?: string }>;
  exact?: boolean;
  permission?: string;
};

export const adminNavItems: AdminNavItem[] = [
  { label: "Dashboard", href: "/admin", icon: HomeIcon, exact: true },
  { label: "Alerts", href: "/admin/alerts", icon: DocumentIcon, exact: false, permission: "reports.view" },
  { label: "POS", href: "/admin/pos", icon: StoreIcon, exact: false },
  { label: "Analytics", href: "/admin/analytics", icon: ChartBarIcon, exact: false },
  { label: "Reports", href: "/admin/reports", icon: DocumentIcon, exact: false },
  { label: "Profits", href: "/admin/profits", icon: ChartBarIcon, exact: false },
  { label: "Orders", href: "/admin/orders", icon: DocumentIcon, exact: false },
  { label: "Fulfillment", href: "/admin/fulfillments", icon: PackageIcon, exact: false },
  { label: "Warehouse", href: "/admin/warehouse", icon: StoreIcon, exact: false, permission: "warehouse.view" },
  { label: "Inventory", href: "/admin/inventory", icon: PackageIcon, exact: false },
  { label: "Shipments", href: "/admin/shipments", icon: ArrowRightIcon, exact: false },
  { label: "Returns", href: "/admin/returns", icon: PackageIcon, exact: false },
  { label: "Support", href: "/admin/support", icon: UserIcon, exact: false, permission: "support.view" },
  { label: "Reviews", href: "/admin/reviews", icon: StarOutlineIcon, exact: false, permission: "reviews.view" },
  { label: "Refunds", href: "/admin/refunds", icon: PackageIcon, exact: false, permission: "refunds.view" },
  { label: "Suppliers", href: "/admin/suppliers", icon: StoreIcon, exact: false },
  { label: "Purchase Orders", href: "/admin/purchase-orders", icon: DocumentIcon, exact: false },
  {
    label: "Stores",
    href: "/admin/stores",
    icon: StoreIcon,
    exact: false,
    permission: "stores.view",
  },
  { label: "Notifications", href: "/admin/notifications", icon: DocumentIcon, exact: false },
  { label: "Templates", href: "/admin/notification-templates", icon: EditIcon, exact: false },
  { label: "Activity Log", href: "/admin/activity-logs", icon: ChartBarIcon, exact: false },
  { label: "Customers", href: "/admin/customers", icon: UserIcon, exact: false },
  { label: "Loyalty", href: "/admin/loyalty", icon: StarOutlineIcon, exact: false },
  { label: "Growth", href: "/admin/growth", icon: ChartBarIcon, exact: false },
  { label: "Promotions", href: "/admin/promotions", icon: TagIcon, exact: false },
];

export const adminSettingsNavItems: AdminNavItem[] = [
  {
    label: "Overview",
    href: "/admin/settings",
    icon: SettingsIcon,
    exact: true,
    permission: "settings.view",
  },
  {
    label: "History",
    href: "/admin/settings/history",
    icon: ChartBarIcon,
    permission: "settings.view",
  },
  { label: "Users", href: "/admin/settings/users", icon: UserIcon, permission: "admins.view" },
  { label: "Roles", href: "/admin/settings/roles", icon: GridIcon, permission: "admins.view" },
  {
    label: "Permissions",
    href: "/admin/settings/permissions",
    icon: TagIcon,
    permission: "roles.manage_permissions",
  },
];

export const adminCatalogNavItems: AdminNavItem[] = [
  { label: "Departments", href: "/admin/departments", icon: StoreIcon },
  { label: "Categories", href: "/admin/categories", icon: TagIcon },
  { label: "Subcategories", href: "/admin/subcategories", icon: LinkIcon },
  { label: "Product Types", href: "/admin/product-types", icon: StarOutlineIcon },
  { label: "Attributes", href: "/admin/attributes", icon: EditIcon },
  { label: "Brands", href: "/admin/brands", icon: GridIcon },
  { label: "Products", href: "/admin/products", icon: PackageIcon },
  {
    label: "Catalog Health",
    href: "/admin/catalog-health",
    icon: ChartBarIcon,
    permission: "catalog.view",
  },
];
