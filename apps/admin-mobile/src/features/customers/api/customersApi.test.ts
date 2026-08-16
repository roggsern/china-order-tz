import { mapCustomer } from './customersApi';

describe('customersApi', () => {
  it('maps customer list item', () => {
    const customer = mapCustomer({
      id: 'c1',
      customer_code: 'CUS-001',
      name: 'Jane Doe',
      email: 'jane@test.com',
      lifecycle_status: 'active',
      metrics: { total_orders: 3, total_spend: 150000 },
    });

    expect(customer.customer_code).toBe('CUS-001');
    expect(customer.metrics?.total_orders).toBe(3);
  });
});
