import { can, canAny, isSuperAdmin, PERMISSIONS } from './permissions';

const admin = {
  id: '1',
  name: 'Staff',
  email: 'staff@test.com',
  is_super_admin: false,
  is_active: true,
  permissions: ['orders.view', 'support.view'],
  role: null,
};

describe('permissions', () => {
  it('isSuperAdmin detects super admins', () => {
    expect(isSuperAdmin({ is_super_admin: true })).toBe(true);
    expect(isSuperAdmin(admin)).toBe(false);
  });

  it('can checks single permission', () => {
    expect(can(admin, PERMISSIONS.ORDERS_VIEW)).toBe(true);
    expect(can(admin, PERMISSIONS.INVENTORY_VIEW)).toBe(false);
  });

  it('canAny checks any permission', () => {
    expect(canAny(admin, [PERMISSIONS.INVENTORY_VIEW, PERMISSIONS.SUPPORT_VIEW])).toBe(true);
    expect(canAny(admin, [PERMISSIONS.INVENTORY_VIEW, PERMISSIONS.CUSTOMERS_VIEW])).toBe(false);
  });

  it('super admin can access everything', () => {
    expect(can({ ...admin, is_super_admin: true }, PERMISSIONS.INVENTORY_VIEW)).toBe(true);
  });
});
