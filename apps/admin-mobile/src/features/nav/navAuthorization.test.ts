import { canAccessMoreLink, canAccessTab, canViewAlerts, visibleTabs } from './navAuthorization';

const admin = {
  id: '1',
  name: 'Ops',
  email: 'ops@test.com',
  is_super_admin: false,
  is_active: true,
  permissions: ['orders.view', 'customers.view'],
  role: null,
};

describe('navAuthorization', () => {
  it('hides support tab without permission', () => {
    expect(canAccessTab(admin, 'support')).toBe(false);
    expect(canAccessTab(admin, 'orders')).toBe(true);
  });

  it('gates more links by permission', () => {
    expect(canAccessMoreLink(admin, 'customers')).toBe(true);
    expect(canAccessMoreLink(admin, 'lowStock')).toBe(false);
    expect(canAccessMoreLink(admin, 'account')).toBe(true);
  });

  it('alerts require reports.view', () => {
    expect(canViewAlerts(admin)).toBe(false);
    expect(canViewAlerts({ ...admin, permissions: ['reports.view'] })).toBe(true);
  });

  it('visibleTabs filters unauthorized tabs', () => {
    expect(visibleTabs(admin)).toEqual(['dashboard', 'orders', 'more']);
  });
});
