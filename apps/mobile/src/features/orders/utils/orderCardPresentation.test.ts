import {
  buildOrderListCardPresentation,
  collectOrderItemImageUrls,
  formatOrderListProductTitle,
} from './orderCardPresentation';
import { mapOrderDetailItem, mapOrderListItem, shouldOfferCancel } from './mapOrders';
import { isOrderPayableFromServer } from './isOrderPayable';

describe('orderCardPresentation', () => {
  it('maps list preview image and multi-item honesty from server preview', () => {
    const order = mapOrderListItem({
      id: 'ord-1',
      order_number: 'COTZ-1001',
      source: 'China',
      status: 'paid',
      status_label: 'Order confirmed',
      payment_status: 'paid',
      currency: 'TZS',
      grand_total: '55000',
      created_at: '2026-08-10T10:00:00Z',
      preview: {
        item_count: 2,
        total_quantity: 3,
        primary_item: {
          name: 'Gown',
          image_url: 'https://cdn.example/gown.jpg',
          quantity: 2,
        },
        extra_items: 1,
      },
    });

    expect(order).not.toBeNull();
    const presentation = buildOrderListCardPresentation(order!);
    expect(presentation.imageUrl).toBe('https://cdn.example/gown.jpg');
    expect(presentation.productName).toBe('Gown');
    expect(presentation.isMultiItem).toBe(true);
    expect(presentation.extraItems).toBe(1);
    expect(formatOrderListProductTitle(presentation)).toBe('Gown +1 more');
    expect(presentation.statusLabel).toBe('Paid');
    expect(presentation.paymentStatus).toBe('Paid');
    expect(presentation.fulfillmentLabel).toBe('Not started');
  });

  it('keeps resolved absolute image urls from relative storage mapping', () => {
    const order = mapOrderListItem({
      id: 'ord-rel',
      status: 'paid',
      preview: {
        item_count: 1,
        primary_item: {
          name: 'Blouse',
          image_url: '/storage/products/blouse.jpg',
          quantity: 1,
        },
        extra_items: 0,
      },
    });
    const presentation = buildOrderListCardPresentation(order!);
    expect(presentation.imageUrl).toMatch(/^https?:\/\//);
    expect(presentation.imageUrl).toContain('/storage/products/blouse.jpg');
  });

  it('does not invent a collage when only primary image exists', () => {
    const order = mapOrderListItem({
      id: 'ord-2',
      order_number: 'COTZ-1002',
      status: 'pending_payment',
      preview: {
        item_count: 3,
        primary_item: {
          name: 'Shirt',
          image_url: 'https://cdn.example/shirt.jpg',
          quantity: 1,
        },
        extra_items: 2,
      },
    });
    const presentation = buildOrderListCardPresentation(order!);
    expect(presentation.imageUrl).toBe('https://cdn.example/shirt.jpg');
    expect(presentation.extraItems).toBe(2);
    expect(presentation.statusLabel).toBe('Awaiting payment');
    expect(presentation.fulfillmentLabel).toBe('Not started');
  });

  it('collects detail item images without inventing urls', () => {
    const withImage = mapOrderDetailItem({
      id: 'line-1',
      product_name: 'Dress',
      quantity: 1,
      product_image_snapshot: 'https://cdn.example/dress.jpg',
    });
    const withoutImage = mapOrderDetailItem({
      id: 'line-2',
      product_name: 'Belt',
      quantity: 1,
    });
    expect(collectOrderItemImageUrls([withImage!, withoutImage!])).toEqual([
      'https://cdn.example/dress.jpg',
    ]);
  });

  it('preserves server can_cancel and payable status authority', () => {
    expect(
      shouldOfferCancel({ status: 'paid', canCancel: false }),
    ).toBe(false);
    expect(
      shouldOfferCancel({ status: 'pending', canCancel: true }),
    ).toBe(true);
    expect(isOrderPayableFromServer({ status: 'pending_payment' })).toBe(true);
    expect(isOrderPayableFromServer({ status: 'paid' })).toBe(false);
  });

  it('shows receiving headline on list cards when the snapshot requires action', () => {
    const order = mapOrderListItem({
      id: 'ord-recv',
      source: 'China',
      status: 'shipped',
      receiving_choice: {
        eligible: true,
        can_select: true,
        selected_method: null,
      },
    });
    const presentation = buildOrderListCardPresentation(order!);
    expect(presentation.statusLabel).toBe('Action required');
  });

  it('does not show Waiting for pickup on completed CHINA_IMPORT list cards', () => {
    const order = mapOrderListItem({
      id: 'ord-china-done',
      source: 'China',
      status: 'completed',
      status_label: 'Completed',
      payment_status: 'paid',
      progress: {
        current_key: 'DELIVERED',
        current_label: 'Completed',
        steps: [],
      },
      receiving_choice: {
        eligible: false,
        can_select: false,
        selected_method: 'self_pickup',
        selected_method_label: 'Self Pickup',
      },
    });
    const presentation = buildOrderListCardPresentation(order!);
    expect(presentation.statusLabel).toBe('Completed');
    expect(presentation.statusLabel).not.toBe('Waiting for pickup');
    expect(presentation.fulfillmentLabel).toBe('Completed');
  });
});
