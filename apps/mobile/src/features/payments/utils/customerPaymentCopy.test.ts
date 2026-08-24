import { ApiError } from '@/src/core/errors';
import {
  NETWORK_TIMEOUT_MESSAGE,
  getSharedTransportErrorMessage,
} from '@/src/core/errors/userFacingNetworkMessage';
import { getCheckoutErrorMessage } from '@/src/features/checkout/utils/checkoutErrorMessage';
import {
  checkoutSessionStatusLabel,
  checkoutShippingChoiceLabel,
} from '@/src/features/checkout/utils/checkoutDisplayLabels';
import { resolveOrderDisplayStatus } from '@/src/features/orders/utils/orderLifecycleDisplay';
import {
  paymentMethodDescription,
  paymentMethodLabel,
} from '@/src/features/payments/utils/paymentAvailability';
import { getPaymentErrorMessage } from '@/src/features/payments/utils/paymentErrorMessage';
import {
  paymentInProgressCustomerMessage,
} from '@/src/features/payments/utils/payNowRecovery';
import { paymentProviderLabel } from '@/src/features/payments/utils/paymentSession';
import {
  PAYMENT_NEXT_STEPS,
  PAYMENT_NEXT_STEPS_TITLE,
  continueToPaymentNote,
  paymentConfirmedSubheading,
  paymentOfficeSubheading,
  paymentProcessingHeading,
  paymentProcessingSubheading,
  paymentSelectorSubheading,
  paymentStatusCardNote,
} from '@/src/features/payments/utils/customerPaymentCopy';
import {
  resolveRefundDisplayStatus,
  resolveReturnDisplayStatus,
} from '@/src/features/returns/utils/returnStatusDisplay';
import { mapProductDetail } from '@/src/features/product/map/mapProduct';

const INTERNAL_COPY = /server|backend|\bAPI\b|transaction|recovery|payment_in_progress|provider|authoritative|selectable|Axios|422|500/i;

function allPaymentCopy(): string[] {
  return [
    paymentSelectorSubheading(),
    paymentProcessingHeading(),
    paymentProcessingSubheading('Mobile Money'),
    paymentProcessingSubheading('NMB'),
    paymentConfirmedSubheading(),
    paymentOfficeSubheading(),
    paymentStatusCardNote(),
    continueToPaymentNote(),
    PAYMENT_NEXT_STEPS_TITLE,
    ...PAYMENT_NEXT_STEPS.map((item) => `${item.title} ${item.description}`),
    paymentMethodLabel('snippe'),
    paymentMethodDescription('snippe'),
    paymentMethodLabel('nmb'),
    paymentMethodDescription('nmb'),
    paymentMethodLabel('cash'),
    paymentMethodDescription('cash'),
    paymentProviderLabel('snippe'),
    paymentProviderLabel('nmb'),
    paymentProviderLabel('cash'),
    paymentInProgressCustomerMessage(),
  ];
}

describe('Wave 8D customer-facing payment copy', () => {
  it('uses customer language on the payment selector', () => {
    expect(paymentSelectorSubheading()).toMatch(/choose how you'd like to pay/i);
    expect(paymentSelectorSubheading()).not.toMatch(INTERNAL_COPY);
  });

  it('uses customer language while payment is processing', () => {
    expect(paymentProcessingHeading()).toMatch(/still processing/i);
    expect(paymentProcessingSubheading('Mobile Money')).toMatch(/safely return later/i);
    expect(paymentProcessingSubheading('Mobile Money')).not.toMatch(INTERNAL_COPY);
  });

  it('uses customer language for payment recovery / continue later', () => {
    const copy = paymentProcessingSubheading('NMB');
    expect(copy).toMatch(/continue your nmb payment/i);
    expect(copy).not.toMatch(/restores it instead|active payment transaction/i);
  });

  it('keeps Pay at Office copy free of administrator language', () => {
    expect(paymentOfficeSubheading()).toMatch(/pay at a china order tz office/i);
    expect(paymentOfficeSubheading()).not.toMatch(/administrator|server/i);
  });

  it('keeps Mobile Money copy free of provider jargon', () => {
    expect(paymentMethodLabel('snippe')).toBe('Mobile Money');
    expect(paymentMethodDescription('snippe')).toMatch(/mobile money/i);
    expect(paymentMethodDescription('snippe')).not.toMatch(/snippe|stk|provider/i);
  });

  it('keeps NMB copy customer-facing', () => {
    expect(paymentMethodLabel('nmb')).toBe('NMB Bank');
    expect(paymentProviderLabel('nmb')).toBe('NMB');
    expect(paymentMethodDescription('nmb')).not.toMatch(INTERNAL_COPY);
  });

  it('maps cancelled and refunded orders to customer labels', () => {
    expect(resolveOrderDisplayStatus({ status: 'cancelled' }).label).toBe('Cancelled');
    expect(resolveOrderDisplayStatus({ status: 'refunded' }).label).toBe('Refunded');
    expect(resolveOrderDisplayStatus({ status: 'refund_pending' }).label).toBe(
      'Refund in progress',
    );
    expect(resolveOrderDisplayStatus({ status: 'pending_payment' }).label).toBe(
      'Awaiting payment',
    );
  });

  it('maps receiving and fulfillment fallbacks without raw enums', () => {
    expect(resolveOrderDisplayStatus({ status: 'ready_for_shipping' }).label).toBe(
      'In progress',
    );
    expect(resolveOrderDisplayStatus({ status: 'ready_for_shipping' }).label).not.toMatch(
      /ready_for_shipping/,
    );
  });

  it('maps return and refund copy without raw status codes', () => {
    expect(resolveReturnDisplayStatus('requested').label).toBe('Return requested');
    expect(resolveRefundDisplayStatus('processing').label).toBe('Refund in progress');
    expect(resolveReturnDisplayStatus('mystery_status').label).toBe('Return in review');
    expect(resolveRefundDisplayStatus('mystery_status').label).toBe(
      'Refund update pending',
    );
  });

  it('maps network timeout to a connection message', () => {
    const message = getSharedTransportErrorMessage(
      new ApiError({ message: 'ignored', status: 0, code: 'timeout' }),
    );
    expect(message).toBe(NETWORK_TIMEOUT_MESSAGE);
    expect(message).toMatch(/couldn't connect/i);
    expect(message).not.toMatch(/timeout|Axios|500/i);
  });

  it('does not expose payment_in_progress', () => {
    const message = getPaymentErrorMessage(
      new ApiError({
        message: 'An active payment is already in progress for this order.',
        status: 422,
        code: 'payment_in_progress',
      }),
    );
    expect(message).toBe('A payment request is already in progress for this order.');
    expect(message).not.toMatch(/payment_in_progress/);
  });

  it('does not expose backend or API terminology in targeted payment copy', () => {
    for (const copy of allPaymentCopy()) {
      expect(copy).not.toMatch(INTERNAL_COPY);
    }
    expect(
      getCheckoutErrorMessage(
        new ApiError({
          message: 'Checkout session has expired. Start checkout again.',
          status: 422,
          code: 'business_rule_violated',
          errors: { session: ['Checkout session has expired.'] },
        }),
      ),
    ).not.toMatch(/session|server|API/i);
    expect(checkoutSessionStatusLabel('shipping_selected')).toBe('Shipping selected');
    expect(checkoutShippingChoiceLabel('company_shipping')).toBe('Company shipping');
  });

  it('keeps brand labels such as ZION MODE', () => {
    const detail = mapProductDetail({
      id: 'p-zion',
      slug: 'essential-knit-top',
      name: 'ESSENTIAL KNIT TOP',
      price: 25000,
      brand: { id: 'b1', slug: 'zion-mode', name: 'ZION MODE' },
      primary_image: { url: 'https://cdn.example/product.jpg' },
    });
    expect(detail?.brand?.name).toBe('ZION MODE');
  });
});
