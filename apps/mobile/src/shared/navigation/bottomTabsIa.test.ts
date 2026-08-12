import {
  BOTTOM_TAB_ROUTES,
  isVisibleBottomTab,
  resolveTabIconName,
  TAB_ICON_NAMES,
} from './tabIcons';
import { browseCatalogKind } from '@/src/features/product/utils/buildSafeProductHref';
import { countPayableOrders, formatTabBadgeCount } from './tabBadges';

describe('bottom navigation IA (Wave 5A)', () => {
  it('exposes exactly Home/Shop/Cart/Orders/Account as visible bottom tabs', () => {
    expect([...BOTTOM_TAB_ROUTES]).toEqual([
      'home',
      'browse',
      'cart',
      'orders',
      'account',
    ]);
    expect(isVisibleBottomTab('search')).toBe(false);
    expect(isVisibleBottomTab('browse')).toBe(true);
  });

  it('keeps Search route icons available for the header entry screen', () => {
    expect(TAB_ICON_NAMES.search.active).toBe('search');
    expect(resolveTabIconName('search', true)).toBe('search');
    expect(Object.keys(TAB_ICON_NAMES)).toContain('search');
  });

  it('preserves Shop journey isolation for China vs TZ', () => {
    expect(browseCatalogKind('CHINA_IMPORT')).toBe('china');
    expect(browseCatalogKind('TZ_LOCAL')).toBe('tz');
  });

  it('preserves cart/orders badge formatting and payable counting', () => {
    expect(formatTabBadgeCount(3)).toBe('3');
    expect(formatTabBadgeCount(0)).toBeUndefined();
    expect(
      countPayableOrders([
        { status: 'pending' },
        { status: 'paid' },
        { status: 'pending_payment' },
      ]),
    ).toBe(2);
  });
});
