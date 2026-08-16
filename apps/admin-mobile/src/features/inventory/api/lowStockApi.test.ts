import { mapLowStockItems } from './lowStockApi';

describe('lowStockApi', () => {
  it('maps low stock rows from server', () => {
    const items = mapLowStockItems([
      {
        variant_inventory_id: 'vi1',
        sku: 'SKU-1',
        product_name: 'Widget',
        available: 2,
        reorder_level: 10,
        status: 'low_stock',
      },
    ]);

    expect(items[0].sku).toBe('SKU-1');
    expect(items[0].status).toBe('low_stock');
  });
});
