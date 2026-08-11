import { formatCustomerDateTime } from './formatCustomerDateTime';

describe('formatCustomerDateTime', () => {
  it('formats ISO timestamps into a localized customer string', () => {
    const formatted = formatCustomerDateTime('2026-08-10T23:28:48.000000Z');
    expect(formatted).not.toBe('2026-08-10T23:28:48.000000Z');
    expect(formatted).toMatch(/2026/);
    expect(formatted).toMatch(/Aug|August|8/);
  });

  it('returns em dash for empty values', () => {
    expect(formatCustomerDateTime(null)).toBe('—');
    expect(formatCustomerDateTime('')).toBe('—');
  });

  it('returns original string when unparseable', () => {
    expect(formatCustomerDateTime('not-a-date')).toBe('not-a-date');
  });
});
