import type {
  PaymentMethodAvailability,
  PaymentMethodsAvailability,
} from '../models/types';

export type MobileSupportedPaymentMethod = 'nmb' | 'snippe' | 'cash';

export const MOBILE_SUPPORTED_PAYMENT_METHODS: readonly MobileSupportedPaymentMethod[] =
  ['nmb', 'snippe', 'cash'];

export type PaymentAvailabilityOption = {
  code: string;
  backendCode: string;
  label: string;
  description: string;
  enabled: boolean;
  available: boolean;
  selectable: boolean;
  supported: boolean;
};

/** Mobile can start only these flows. Backend may expose others; do not invent starts. */
export function isMobileSupportedPaymentMethod(
  code: string,
): code is MobileSupportedPaymentMethod {
  return (MOBILE_SUPPORTED_PAYMENT_METHODS as readonly string[]).includes(code);
}

const KNOWN_METHOD_COPY: Record<string, { label: string; description: string }> = {
  nmb: {
    label: 'NMB Bank',
    description: 'Pay via NMB mobile banking',
  },
  snippe: {
    label: 'Mobile Money',
    description: 'Pay with Mobile Money on your phone.',
  },
  cash: {
    label: 'Pay at Office',
    description:
      'Place your order now and pay at a CHINA ORDER TZ office. Your order will be processed after payment is confirmed.',
  },
  mpesa: {
    label: 'M-Pesa',
    description: 'Pay with M-Pesa on your Vodacom number',
  },
  card: {
    label: 'Visa / Mastercard',
    description: 'Secure card payment',
  },
  bank_transfer: {
    label: 'Bank Transfer',
    description: 'Transfer to our account (details after order)',
  },
};

function methodByCode(
  availability: PaymentMethodsAvailability,
  code: string,
): PaymentMethodAvailability | undefined {
  return availability.methods.find((method) => method.code === code);
}

/** Trust backend `selectable` only — never invent NMB/cash/Snippe rules. */
export function isPaymentMethodSelectable(
  method: Pick<PaymentMethodAvailability, 'selectable'>,
): boolean {
  return method.selectable === true;
}

export function paymentMethodLabel(backendCode: string): string {
  return KNOWN_METHOD_COPY[backendCode]?.label ?? backendCode.replace(/_/g, ' ');
}

export function paymentMethodDescription(backendCode: string): string {
  return KNOWN_METHOD_COPY[backendCode]?.description ?? 'Available at checkout';
}

/**
 * Build selector options from mapped GET /payments/methods.
 * A method appears only when it is in `enabledMethods` and backend-selectable.
 * Unknown future codes are kept when selectable — they are not dropped or forced off.
 */
export function buildSelectablePaymentOptions(
  availability: PaymentMethodsAvailability,
): PaymentAvailabilityOption[] {
  const selectableCodes = new Set(
    availability.methods.filter(isPaymentMethodSelectable).map((method) => method.code),
  );

  const options: PaymentAvailabilityOption[] = [];

  for (const backendCode of availability.enabledMethods) {
    if (!selectableCodes.has(backendCode)) {
      continue;
    }

    const row = methodByCode(availability, backendCode);
    options.push({
      code: backendCode,
      backendCode,
      label: paymentMethodLabel(backendCode),
      description: paymentMethodDescription(backendCode),
      enabled: row?.enabled ?? true,
      available: row?.available ?? true,
      selectable: true,
      supported: isMobileSupportedPaymentMethod(backendCode),
    });
  }

  return options;
}

export function resolveDefaultPaymentCode(
  availability: PaymentMethodsAvailability,
  options: PaymentAvailabilityOption[],
): string | null {
  if (options.length === 0) {
    return null;
  }

  const preferred = availability.defaultProvider;
  if (
    preferred &&
    options.some((option) => option.code === preferred && option.supported)
  ) {
    return preferred;
  }

  return options.find((option) => option.supported)?.code ?? options[0]?.code ?? null;
}

export function selectablePaymentCodes(
  availability: PaymentMethodsAvailability,
): string[] {
  return buildSelectablePaymentOptions(availability).map((option) => option.code);
}
