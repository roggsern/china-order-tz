import {
  buildDeliveryAddressPayload,
  buildShippingChoicePayload,
  isReadyForPayment,
  isStaleOrExpiredCheckoutError,
  journeyLabelFromCheckoutItems,
  mapCheckoutPrepare,
  mapCheckoutSession,
  shippingChoicesForItems,
} from './mapCheckout';
import { ApiError } from '@/src/core/errors';

describe('mapCheckoutPrepare', () => {
  it('maps prepare resource and preserves server totals', () => {
    const prepare = mapCheckoutPrepare({
      customer: {
        first_name: 'Asha',
        last_name: 'Juma',
        email: 'asha@example.com',
        phone: '+255700000000',
      },
      delivery_address: {
        recipient_name: 'Asha Juma',
        phone: '+255700000000',
        country: 'Tanzania',
        region: 'Dar',
        city: 'Dar es Salaam',
        district: 'Ilala',
        street: 'Samora Ave',
      },
      items: [
        {
          id: 'line-1',
          product_id: 'p1',
          product_name: 'Phone',
          quantity: 2,
          unit_price: '500000',
          subtotal: '1000000',
          source: 'China',
          shipping_method: 'air',
          shipping_price: '20000',
          shipping_subtotal: '40000',
        },
      ],
      subtotal: '1000000',
      shipping_summary: {
        china_shipping_total: '40000',
      },
      grand_total: '1040000',
      ready_for_confirmation: false,
    });

    expect(prepare.items).toHaveLength(1);
    expect(prepare.subtotal).toBe('1000000');
    expect(prepare.grandTotal).toBe('1040000');
    expect(prepare.shippingSummary.chinaShippingTotal).toBe('40000');
    expect(prepare.deliveryAddress.city).toBe('Dar es Salaam');
    expect(prepare.items[0]?.source).toBe('China');
  });
});

describe('mapCheckoutSession', () => {
  it('maps session fields including shipping readiness and expiry', () => {
    const session = mapCheckoutSession({
      id: 'sess-1',
      cart_id: 'cart-1',
      currency: 'TZS',
      status: 'validated',
      subtotal: '1000',
      discount_total: '0',
      tax_total: '0',
      shipping_total: '100',
      grand_total: '1100',
      shipping_choice: 'company_shipping',
      shipping_method: 'air',
      shipping_ready: true,
      is_expired: false,
      expires_at: '2026-08-10T12:00:00Z',
    });

    expect(session).toMatchObject({
      id: 'sess-1',
      status: 'validated',
      shippingChoice: 'company_shipping',
      shippingMethod: 'air',
      shippingReady: true,
      isExpired: false,
      grandTotal: '1100',
    });
  });
});

describe('buildShippingChoicePayload', () => {
  it('builds company shipping payload with air/sea method', () => {
    expect(
      buildShippingChoicePayload({
        shippingChoice: 'company_shipping',
        shippingMethod: 'air',
      }),
    ).toEqual({
      shipping_choice: 'company_shipping',
      shipping_method: 'air',
      agent_name: null,
      agent_contact: null,
    });
  });

  it('builds customer agent payload', () => {
    expect(
      buildShippingChoicePayload({
        shippingChoice: 'customer_agent',
        agentName: 'John',
        agentContact: '+255711',
      }),
    ).toEqual({
      shipping_choice: 'customer_agent',
      shipping_method: null,
      agent_name: 'John',
      agent_contact: '+255711',
    });
  });

  it('builds TZ self pickup payload', () => {
    expect(
      buildShippingChoicePayload({
        shippingChoice: 'self_pickup',
      }),
    ).toEqual({
      shipping_choice: 'self_pickup',
      shipping_method: null,
      agent_name: null,
      agent_contact: null,
    });
  });
});

describe('CHINA_IMPORT / TZ_LOCAL shipping choices', () => {
  it('offers China choices from China source items', () => {
    const choices = shippingChoicesForItems([
      {
        id: '1',
        productId: 'p',
        productName: 'A',
        quantity: 1,
        unitPrice: 1,
        lineSubtotal: 1,
        source: 'China',
        shippingMethod: null,
        shippingPrice: null,
        shippingSubtotal: null,
        deliveryStatus: null,
      },
    ]);
    expect(choices.map((c) => c.value)).toEqual([
      'company_shipping',
      'customer_agent',
    ]);
    expect(journeyLabelFromCheckoutItems([
      {
        id: '1',
        productId: 'p',
        productName: 'A',
        quantity: 1,
        unitPrice: 1,
        lineSubtotal: 1,
        source: 'China',
        shippingMethod: null,
        shippingPrice: null,
        shippingSubtotal: null,
        deliveryStatus: null,
      },
    ])).toBe('Order from China');
  });

  it('offers TZ choices from Dar source items', () => {
    const choices = shippingChoicesForItems([
      {
        id: '1',
        productId: 'p',
        productName: 'A',
        quantity: 1,
        unitPrice: 1,
        lineSubtotal: 1,
        source: 'Dar',
        shippingMethod: null,
        shippingPrice: null,
        shippingSubtotal: null,
        deliveryStatus: 'To Be Negotiated',
      },
    ]);
    expect(choices.map((c) => c.value)).toEqual([
      'self_pickup',
      'negotiated_delivery',
    ]);
    expect(journeyLabelFromCheckoutItems(choices.length ? [
      {
        id: '1',
        productId: 'p',
        productName: 'A',
        quantity: 1,
        unitPrice: 1,
        lineSubtotal: 1,
        source: 'Dar',
        shippingMethod: null,
        shippingPrice: null,
        shippingSubtotal: null,
        deliveryStatus: null,
      },
    ] : [])).toBe('Buy from TZ');
  });
});

describe('ready-for-payment / stale session', () => {
  it('requires shipping choice and non-expired session', () => {
    expect(
      isReadyForPayment({
        id: 's1',
        cartId: 'c1',
        currency: 'TZS',
        status: 'validated',
        subtotal: '1',
        discountTotal: '0',
        taxTotal: '0',
        shippingTotal: '0',
        grandTotal: '1',
        shippingChoice: 'self_pickup',
        shippingMethod: null,
        agentName: null,
        agentContact: null,
        shippingReady: true,
        isExpired: false,
        expiresAt: null,
      }),
    ).toBe(true);

    expect(
      isReadyForPayment({
        id: 's1',
        cartId: 'c1',
        currency: 'TZS',
        status: 'expired',
        subtotal: '1',
        discountTotal: '0',
        taxTotal: '0',
        shippingTotal: '0',
        grandTotal: '1',
        shippingChoice: 'self_pickup',
        shippingMethod: null,
        agentName: null,
        agentContact: null,
        shippingReady: true,
        isExpired: true,
        expiresAt: null,
      }),
    ).toBe(false);
  });

  it('detects stale/expired checkout errors from server messages', () => {
    expect(
      isStaleOrExpiredCheckoutError(
        new ApiError({
          message: 'Checkout totals are stale. Refresh checkout and confirm shipping again.',
          status: 422,
          code: 'business_rule_violated',
          errors: { session: ['Checkout totals are stale.'] },
        }),
      ),
    ).toBe(true);

    expect(
      isStaleOrExpiredCheckoutError(
        new ApiError({
          message: 'Checkout session has expired.',
          status: 422,
          code: 'business_rule_violated',
        }),
      ),
    ).toBe(true);
  });
});

describe('buildDeliveryAddressPayload', () => {
  it('maps address fields for PATCH /profile/address', () => {
    expect(
      buildDeliveryAddressPayload({
        recipientName: 'Asha',
        phone: '+255700000000',
        country: 'Tanzania',
        region: 'Dar',
        city: 'DSM',
        district: 'Ilala',
        street: 'Main',
      }),
    ).toEqual({
      recipient_name: 'Asha',
      phone: '+255700000000',
      country: 'Tanzania',
      region: 'Dar',
      city: 'DSM',
      district: 'Ilala',
      street: 'Main',
      landmark: null,
      postal_code: null,
    });
  });
});
