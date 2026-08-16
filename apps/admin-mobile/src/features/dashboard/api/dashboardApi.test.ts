import { mapAlertsResponse, mapDashboardResponse } from './dashboardApi';

describe('dashboardApi mappers', () => {
  it('maps dashboard server fields with safe optional sections', () => {
    const dashboard = mapDashboardResponse({
      overview: {
        orders_today: 12,
        revenue_today: 500000,
        pending_actions: 3,
      },
      attention_items: [{ key: 'qc_pending', label: 'QC pending', count: 2 }],
      china_pipeline: { qc_pending: 2 },
      tz_local: { ready_to_ship: 1 },
      operations: {
        fulfillment_queue: { total: 5, china: 2, local: 3 },
        open_returns: 1,
      },
    });

    expect(dashboard.overview?.orders_today).toBe(12);
    expect(dashboard.attention_items?.[0]?.count).toBe(2);
    expect(dashboard.operations?.fulfillment_queue?.total).toBe(5);
  });

  it('maps alerts payload', () => {
    const alerts = mapAlertsResponse({
      counts: { total: 1 },
      alerts: [
        {
          severity: 'warning',
          title: 'Low stock',
          message: 'SKUs below reorder',
          source: 'operational',
        },
      ],
    });

    expect(alerts.alerts?.[0]?.title).toBe('Low stock');
  });
});
