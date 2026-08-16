import { mapAdminResource, loginAdmin, fetchCurrentAdmin, logoutAdmin } from './adminAuthApi';

describe('adminAuthApi mappers', () => {
  it('maps admin resource from login/me payload', () => {
    const admin = mapAdminResource({
      id: 'a1',
      name: 'Ops Lead',
      email: 'ops@chinaordertz.com',
      phone: '+255700000000',
      is_super_admin: false,
      is_active: true,
      permissions: ['orders.view', 'support.view'],
      role: { id: 'r1', name: 'Support', slug: 'support' },
    });

    expect(admin).toEqual({
      id: 'a1',
      name: 'Ops Lead',
      email: 'ops@chinaordertz.com',
      phone: '+255700000000',
      is_super_admin: false,
      is_active: true,
      permissions: ['orders.view', 'support.view'],
      role: { id: 'r1', name: 'Support', slug: 'support' },
    });
  });
});

describe('adminAuthApi endpoints', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = jest.fn();
  });

  it('login maps token and admin', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          success: true,
          token: 'tok',
          token_type: 'Bearer',
          data: {
            id: 'a1',
            name: 'Admin',
            email: 'admin@test.com',
            is_super_admin: true,
            is_active: true,
            permissions: [],
            role: null,
          },
        }),
    });

    const result = await loginAdmin({ email: 'admin@test.com', password: 'password123' });
    expect(result.token).toBe('tok');
    expect(result.admin.email).toBe('admin@test.com');
  });

  it('me maps current admin', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          success: true,
          data: {
            id: 'a1',
            name: 'Admin',
            email: 'admin@test.com',
            is_super_admin: false,
            is_active: true,
            permissions: ['orders.view'],
            role: null,
          },
        }),
    });

    const admin = await fetchCurrentAdmin();
    expect(admin.permissions).toEqual(['orders.view']);
  });

  it('logout posts to server', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ success: true, message: 'Logged out successfully' }),
    });

    await logoutAdmin();
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/logout'),
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('logout includes installation_id when provided', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ success: true, message: 'Logged out successfully' }),
    });

    await logoutAdmin({ installation_id: '11111111-1111-4111-8111-111111111111' });
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/logout'),
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          installation_id: '11111111-1111-4111-8111-111111111111',
        }),
      }),
    );
  });
});
