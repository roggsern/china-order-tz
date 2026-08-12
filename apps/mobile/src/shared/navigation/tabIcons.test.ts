import { resolveTabIconName, TAB_ICON_NAMES, BOTTOM_TAB_ROUTES } from './tabIcons';

describe('resolveTabIconName', () => {
  it('maps each primary tab to a release-safe Ionicons glyph', () => {
    expect(resolveTabIconName('home', true)).toBe('home');
    expect(resolveTabIconName('home', false)).toBe('home-outline');
    expect(resolveTabIconName('search', true)).toBe('search');
    expect(resolveTabIconName('browse', true)).toBe('grid');
    expect(resolveTabIconName('cart', false)).toBe('cart-outline');
    expect(resolveTabIconName('orders', true)).toBe('receipt');
    expect(resolveTabIconName('account', false)).toBe('person-outline');
  });

  it('covers configured tab routes including hidden Search', () => {
    expect(Object.keys(TAB_ICON_NAMES).sort()).toEqual(
      ['account', 'browse', 'cart', 'home', 'orders', 'search'].sort(),
    );
    expect([...BOTTOM_TAB_ROUTES]).toEqual([
      'home',
      'browse',
      'cart',
      'orders',
      'account',
    ]);
  });

  it('falls back for unknown routes without leaving the icon undefined', () => {
    expect(resolveTabIconName('unknown', true)).toBe('ellipse');
    expect(resolveTabIconName('unknown', false)).toBe('ellipse-outline');
  });
});
