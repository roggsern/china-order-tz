import {
  formatSnippePhoneForInput,
  normalizeSnippePhone,
  validateSnippePhoneInput,
} from './snippePhone';

describe('Snippe phone validation', () => {
  it('accepts common Tanzania mobile formats', () => {
    expect(normalizeSnippePhone('0712345678')).toBe('255712345678');
    expect(normalizeSnippePhone('+255712345678')).toBe('255712345678');
    expect(normalizeSnippePhone('255712345678')).toBe('255712345678');
    expect(validateSnippePhoneInput('0712345678')).toBeNull();
  });

  it('rejects empty or invalid numbers before start', () => {
    expect(validateSnippePhoneInput('')).toMatch(/enter your mobile money number/i);
    expect(validateSnippePhoneInput('123')).toMatch(/valid Tanzania mobile/i);
    expect(normalizeSnippePhone('0212345678')).toBeNull();
  });

  it('formats a normalized number for the payment input without a second parser', () => {
    expect(formatSnippePhoneForInput('0712345678')).toBe('0712345678');
    expect(formatSnippePhoneForInput('+255712345678')).toBe('0712345678');
    expect(formatSnippePhoneForInput('255712345678')).toBe('0712345678');
    expect(formatSnippePhoneForInput('0212345678')).toBeNull();
    expect(formatSnippePhoneForInput('')).toBeNull();
  });
});
