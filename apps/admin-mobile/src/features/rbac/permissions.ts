import type { AdminIdentity } from '@/src/core/auth/types';

export const PERMISSIONS = {
  ORDERS_VIEW: 'orders.view',
  SUPPORT_VIEW: 'support.view',
  CUSTOMERS_VIEW: 'customers.view',
  INVENTORY_VIEW: 'inventory.view',
  REPORTS_VIEW: 'reports.view',
} as const;

export function isSuperAdmin(admin: Pick<AdminIdentity, 'is_super_admin'> | null | undefined): boolean {
  return admin?.is_super_admin === true;
}

export function can(
  admin: Pick<AdminIdentity, 'permissions' | 'is_super_admin'> | null | undefined,
  permission: string,
): boolean {
  if (!admin) return false;
  if (isSuperAdmin(admin)) return true;
  return admin.permissions.includes(permission);
}

export function canAny(
  admin: Pick<AdminIdentity, 'permissions' | 'is_super_admin'> | null | undefined,
  permissions: string[],
): boolean {
  return permissions.some((permission) => can(admin, permission));
}
