import { mapPaymentMethods } from './mapPayment';
import {
  buildSelectablePaymentOptions,
  isPaymentMethodSelectable,
  resolveDefaultPaymentCode,
  selectablePaymentCodes,
} from './paymentAvailability';

function availability(raw: Record<string, unknown>) {
  return mapPaymentMethods(raw);
}

describe('payment availability adapter', () => {
  it('keeps backend-selectable NMB selectable', () => {
    const options = buildSelectablePaymentOptions(
      availability({
        default_provider: 'nmb',
        enabled_methods: ['nmb'],
        methods: [{ code: 'nmb', enabled: true, available: true, selectable: true }],
      }),
    );

    expect(options.map((option) => option.code)).toEqual(['nmb']);
    expect(options[0]?.selectable).toBe(true);
  });

  it('keeps backend-selectable Snippe selectable', () => {
    const options = buildSelectablePaymentOptions(
      availability({
        default_provider: 'nmb',
        enabled_methods: ['nmb', 'snippe'],
        methods: [
          { code: 'nmb', enabled: true, available: true, selectable: true },
          { code: 'snippe', enabled: true, available: true, selectable: true },
        ],
      }),
    );

    expect(selectablePaymentCodes(
      availability({
        default_provider: 'nmb',
        enabled_methods: ['nmb', 'snippe'],
        methods: [
          { code: 'nmb', enabled: true, available: true, selectable: true },
          { code: 'snippe', enabled: true, available: true, selectable: true },
        ],
      }),
    )).toEqual(['nmb', 'snippe']);
    expect(options.find((option) => option.code === 'snippe')?.label).toBe('Mobile Money');
  });

  it('keeps backend-selectable cash selectable as Pay at Office', () => {
    const options = buildSelectablePaymentOptions(
      availability({
        default_provider: 'nmb',
        enabled_methods: ['nmb', 'cash'],
        methods: [
          { code: 'nmb', enabled: true, available: true, selectable: true },
          { code: 'cash', enabled: true, available: true, selectable: true },
        ],
      }),
    );

    const cash = options.find((option) => option.code === 'cash');
    expect(cash?.backendCode).toBe('cash');
    expect(cash?.selectable).toBe(true);
    expect(cash?.label).toBe('Pay at Office');
  });

  it('hides cash when backend does not mark it selectable', () => {
    const options = buildSelectablePaymentOptions(
      availability({
        default_provider: 'nmb',
        enabled_methods: ['nmb', 'cash'],
        methods: [
          { code: 'nmb', enabled: true, available: true, selectable: true },
          { code: 'cash', enabled: false, available: true, selectable: false },
        ],
      }),
    );

    expect(options.some((option) => option.code === 'cash')).toBe(false);
    expect(options.map((option) => option.code)).toEqual(['nmb']);
  });

  it('does not override backend selectable state', () => {
    const mapped = availability({
      default_provider: 'nmb',
      enabled_methods: ['nmb', 'snippe', 'cash'],
      methods: [
        { code: 'nmb', enabled: true, available: true, selectable: true },
        { code: 'snippe', enabled: true, available: true, selectable: false },
        { code: 'cash', enabled: true, available: true, selectable: true },
      ],
    });

    expect(isPaymentMethodSelectable(mapped.methods[0]!)).toBe(true);
    expect(isPaymentMethodSelectable(mapped.methods[1]!)).toBe(false);
    expect(isPaymentMethodSelectable(mapped.methods[2]!)).toBe(true);
    expect(selectablePaymentCodes(mapped)).toEqual(['nmb', 'cash']);
  });

  it('maps unknown future methods without crashing or dropping them', () => {
    const mapped = availability({
      default_provider: 'future_pay',
      enabled_methods: ['future_pay'],
      methods: [
        { code: 'future_pay', enabled: true, available: true, selectable: true },
      ],
    });

    const options = buildSelectablePaymentOptions(mapped);
    expect(options).toHaveLength(1);
    expect(options[0]).toMatchObject({
      code: 'future_pay',
      backendCode: 'future_pay',
      selectable: true,
    });
    expect(resolveDefaultPaymentCode(mapped, options)).toBe('future_pay');
  });

  it('prefers backend default_provider when that method is selectable', () => {
    const mapped = availability({
      default_provider: 'snippe',
      enabled_methods: ['nmb', 'snippe', 'cash'],
      methods: [
        { code: 'nmb', enabled: true, available: true, selectable: true },
        { code: 'snippe', enabled: true, available: true, selectable: true },
        { code: 'cash', enabled: true, available: true, selectable: true },
      ],
    });
    const options = buildSelectablePaymentOptions(mapped);
    expect(resolveDefaultPaymentCode(mapped, options)).toBe('snippe');
  });
});
