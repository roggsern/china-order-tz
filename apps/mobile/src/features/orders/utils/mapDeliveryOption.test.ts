import {
  buildSelectDeliveryOptionPayload,
  buildUpdateDeliveryOptionPayload,
  canManagePostPayDeliveryOption,
  isDeliveryOptionLocked,
  mapDeliveryOptionShow,
} from './mapDeliveryOption';

describe('mapDeliveryOptionShow', () => {
  it('maps backend delivery-option state without inventing a fee', () => {
    const show = mapDeliveryOptionShow({
      delivery_option: {
        id: 'do-1',
        order_id: 'ord-1',
        delivery_type: 'company_shipping',
        delivery_type_label: 'Company shipping',
        shipping_method: 'air',
        shipping_method_label: 'Air',
        delivery_status: 'pending',
        delivery_status_label: 'Pending',
        last_mile_receiving_method: 'self_pickup',
        last_mile_receiving_method_label: 'Self Pickup',
      },
      available: {
        market: 'china',
        market_label: 'China',
        delivery_types: [{ value: 'company_shipping', label: 'Company shipping' }],
        shipping_methods: [{ value: 'air', label: 'Air' }],
      },
    });

    expect(show.deliveryOption).toMatchObject({
      id: 'do-1',
      deliveryType: 'company_shipping',
      shippingMethod: 'air',
      deliveryStatus: 'pending',
      lastMileReceivingMethod: 'self_pickup',
    });
    expect(show.available?.market).toBe('china');
    expect(JSON.stringify(show)).not.toMatch(/fee|total|price/i);
  });

  it('maps a TZ_LOCAL available set from backend types only', () => {
    const show = mapDeliveryOptionShow({
      delivery_option: {
        id: 'do-tz',
        delivery_type: 'self_pickup',
        delivery_status: 'confirmed',
      },
      available: {
        market: 'tanzania',
        market_label: 'Tanzania',
        delivery_types: [
          { value: 'self_pickup', label: 'Self pickup' },
          { value: 'negotiated_delivery', label: 'Negotiated delivery' },
        ],
        shipping_methods: [],
      },
    });
    expect(show.available?.deliveryTypes.map((row) => row.value)).toEqual([
      'self_pickup',
      'negotiated_delivery',
    ]);
    expect(show.available?.shippingMethods).toEqual([]);
  });
});

describe('delivery option payloads', () => {
  it('posts backend-supported select fields', () => {
    expect(
      buildSelectDeliveryOptionPayload({
        deliveryType: 'customer_agent',
        agentName: 'Agent A',
        agentContact: '+2551',
      }),
    ).toEqual({
      delivery_type: 'customer_agent',
      shipping_method: null,
      agent_name: 'Agent A',
      agent_contact: '+2551',
      notes: null,
    });
  });

  it('patches confirm without changing paid totals', () => {
    expect(
      buildUpdateDeliveryOptionPayload({ deliveryStatus: 'confirmed' }),
    ).toEqual({ delivery_status: 'confirmed' });
  });
});

describe('canManagePostPayDeliveryOption', () => {
  it('follows the web paid/open contract and hides terminal orders', () => {
    expect(
      canManagePostPayDeliveryOption({
        status: 'processing',
        paymentStatus: 'paid',
      }),
    ).toBe(true);
    expect(
      canManagePostPayDeliveryOption({
        status: 'cancelled',
        paymentStatus: 'paid',
      }),
    ).toBe(false);
    expect(
      canManagePostPayDeliveryOption({
        status: 'refunded',
        paymentStatus: 'refunded',
      }),
    ).toBe(false);
  });

  it('locks completed delivery options', () => {
    expect(
      isDeliveryOptionLocked({
        id: 'do-1',
        orderId: 'ord-1',
        deliveryType: 'company_shipping',
        deliveryTypeLabel: 'Company shipping',
        shippingMethod: 'air',
        shippingMethodLabel: 'Air',
        deliveryStatus: 'completed',
        deliveryStatusLabel: 'Completed',
        lastMileReceivingMethod: null,
        lastMileReceivingMethodLabel: null,
        agentName: null,
        agentContact: null,
        notes: null,
        confirmedAt: null,
      }),
    ).toBe(true);
  });
});
