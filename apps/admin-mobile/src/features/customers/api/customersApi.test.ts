import { buildCustomersQuery, mapCustomer } from './customersApi';

describe('customersApi', () => {
  it('maps customer with Laravel decimal-string total_spend', () => {
    const customer = mapCustomer({
      id: 'c1',
      customer_code: 'CUS-001',
      name: 'Jane Doe',
      email: 'jane@test.com',
      lifecycle_status: 'active',
      metrics: { total_orders: 3, total_spend: '150000.00', last_order_at: '2026-01-01T00:00:00Z' },
    });

    expect(customer.customer_code).toBe('CUS-001');
    expect(customer.metrics?.total_orders).toBe(3);
    expect(customer.metrics?.total_spend).toBe(150000);
  });

  it('maps customer when total_spend is a JSON number', () => {
    const customer = mapCustomer({
      id: 'c1',
      customer_code: 'CUS-001',
      name: 'Jane Doe',
      metrics: { total_orders: 1, total_spend: 50000 },
    });

    expect(customer.metrics?.total_spend).toBe(50000);
  });

  it('rejects malformed total_spend', () => {
    expect(() =>
      mapCustomer({
        id: 'c1',
        metrics: { total_spend: 'abc' },
      }),
    ).toThrow();
  });

  it('builds customer list query with search (not q)', () => {
    expect(buildCustomersQuery({ search: 'Rogson', page: 1 })).toEqual({
      page: 1,
      per_page: 20,
      search: 'Rogson',
    });
    expect(buildCustomersQuery({ search: '  Rogson  ', page: 2, per_page: 20 })).toEqual({
      page: 2,
      per_page: 20,
      search: 'Rogson',
    });
    expect(Object.keys(buildCustomersQuery({ search: 'Rogson' }))).not.toContain('q');
  });

  it('omits search when empty', () => {
    expect(buildCustomersQuery({ search: '   ', page: 1 })).toEqual({
      page: 1,
      per_page: 20,
    });
  });
});
