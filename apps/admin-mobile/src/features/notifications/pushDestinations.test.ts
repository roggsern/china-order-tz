import { ADMIN_DASHBOARD_HREF, resolveAdminPushDestination } from './pushDestinations';

const ORDER_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const TICKET_ID = 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee';

describe('resolveAdminPushDestination', () => {
  it('maps admin.dashboard', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.dashboard' })).toBe(
      ADMIN_DASHBOARD_HREF,
    );
  });

  it('maps admin.orders', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.orders' })).toBe(
      '/(app)/(tabs)/orders',
    );
  });

  it('maps admin.order_detail with order_id', () => {
    expect(
      resolveAdminPushDestination({
        destination: 'admin.order_detail',
        order_id: ORDER_ID,
      }),
    ).toBe(`/(app)/(tabs)/orders/${ORDER_ID}`);
  });

  it('maps admin.support', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.support' })).toBe(
      '/(app)/(tabs)/support',
    );
  });

  it('maps admin.support_ticket with ticket_id', () => {
    expect(
      resolveAdminPushDestination({
        destination: 'admin.support_ticket',
        ticket_id: TICKET_ID,
      }),
    ).toBe(`/(app)/(tabs)/support/${TICKET_ID}`);
  });

  it('maps admin.low_stock', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.low_stock' })).toBe(
      '/(app)/(tabs)/more/low-stock',
    );
  });

  it('returns null for unknown destination', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.unknown' })).toBeNull();
  });

  it('returns null when order_detail missing order_id', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.order_detail' })).toBeNull();
  });

  it('returns null when support_ticket missing ticket_id', () => {
    expect(resolveAdminPushDestination({ destination: 'admin.support_ticket' })).toBeNull();
  });

  it('returns null when destination missing', () => {
    expect(resolveAdminPushDestination({ order_id: ORDER_ID })).toBeNull();
  });
});
