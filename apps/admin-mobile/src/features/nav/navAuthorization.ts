import { can, PERMISSIONS } from '@/src/features/rbac/permissions';
import type { AdminIdentity } from '@/src/core/auth/types';

export type AdminTabKey = 'dashboard' | 'orders' | 'support' | 'more';

export type MoreLinkKey = 'customers' | 'lowStock' | 'account';

export function canAccessTab(admin: AdminIdentity | null, tab: AdminTabKey): boolean {
  if (!admin) return false;
  switch (tab) {
    case 'dashboard':
      return true;
    case 'orders':
      return can(admin, PERMISSIONS.ORDERS_VIEW);
    case 'support':
      return can(admin, PERMISSIONS.SUPPORT_VIEW);
    case 'more':
      return true;
    default:
      return false;
  }
}

export function canAccessMoreLink(admin: AdminIdentity | null, link: MoreLinkKey): boolean {
  if (!admin) return false;
  switch (link) {
    case 'customers':
      return can(admin, PERMISSIONS.CUSTOMERS_VIEW);
    case 'lowStock':
      return can(admin, PERMISSIONS.INVENTORY_VIEW);
    case 'account':
      return true;
    default:
      return false;
  }
}

export function canViewAlerts(admin: AdminIdentity | null): boolean {
  return can(admin, PERMISSIONS.REPORTS_VIEW);
}

export function visibleTabs(admin: AdminIdentity | null): AdminTabKey[] {
  const tabs: AdminTabKey[] = ['dashboard', 'orders', 'support', 'more'];
  return tabs.filter((tab) => canAccessTab(admin, tab));
}
