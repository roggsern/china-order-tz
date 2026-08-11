import { normalizeCustomerPlainText } from './normalizeCustomerPlainText';

describe('normalizeCustomerPlainText', () => {
  it('decodes common HTML entities without executing HTML', () => {
    expect(normalizeCustomerPlainText('A &amp; B')).toBe('A & B');
    expect(normalizeCustomerPlainText('Tom &amp;amp; Jerry')).toBe('Tom & Jerry');
    expect(normalizeCustomerPlainText('&lt;b&gt;bold&lt;/b&gt;')).toBe('bold');
  });

  it('strips tags and collapses whitespace', () => {
    expect(normalizeCustomerPlainText('<p>Hello <strong>world</strong></p>')).toBe(
      'Hello world',
    );
  });

  it('returns empty for blank input', () => {
    expect(normalizeCustomerPlainText(null)).toBe('');
    expect(normalizeCustomerPlainText('   ')).toBe('');
  });
});
