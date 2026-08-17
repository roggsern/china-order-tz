import { moneySchema, normalizeMoneyTransport, nullishMoneySchema, optionalMoneySchema } from './money';

describe('money transport boundary', () => {
  it('normalizes Laravel decimal strings', () => {
    expect(normalizeMoneyTransport('58000.00')).toBe(58000);
    expect(normalizeMoneyTransport('0.00')).toBe(0);
  });

  it('accepts finite JSON numbers', () => {
    expect(normalizeMoneyTransport(58000)).toBe(58000);
    expect(normalizeMoneyTransport(0)).toBe(0);
  });

  it('rejects malformed strings and non-finite numbers', () => {
    expect(() => normalizeMoneyTransport('')).toThrow();
    expect(() => normalizeMoneyTransport('  ')).toThrow();
    expect(() => normalizeMoneyTransport('abc')).toThrow();
    expect(() => normalizeMoneyTransport('12.34.56')).toThrow();
    expect(() => normalizeMoneyTransport(Number.NaN)).toThrow();
    expect(() => normalizeMoneyTransport(null)).toThrow();
    expect(() => normalizeMoneyTransport(undefined)).toThrow();
  });

  it('moneySchema parses string and number', () => {
    expect(moneySchema.parse('15000.00')).toBe(15000);
    expect(moneySchema.parse(15000)).toBe(15000);
  });

  it('moneySchema rejects invalid values', () => {
    expect(() => moneySchema.parse('not-money')).toThrow();
    expect(() => moneySchema.parse({})).toThrow();
  });

  it('optionalMoneySchema allows undefined only', () => {
    expect(optionalMoneySchema.parse(undefined)).toBeUndefined();
    expect(optionalMoneySchema.parse('10.00')).toBe(10);
    expect(() => optionalMoneySchema.parse(null)).toThrow();
  });

  it('nullishMoneySchema allows null and undefined', () => {
    expect(nullishMoneySchema.parse(null)).toBeNull();
    expect(nullishMoneySchema.parse(undefined)).toBeUndefined();
    expect(nullishMoneySchema.parse('9.50')).toBe(9.5);
  });
});
