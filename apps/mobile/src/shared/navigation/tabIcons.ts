/**
 * Release-safe tab icon names for @expo/vector-icons Ionicons.
 * Kept free of React Native imports for unit testing.
 *
 * Visible bottom tabs: Home / Shop(browse) / Cart / Orders / Account.
 * Search remains a routable screen (header entry) but is hidden from the tab bar.
 */
export const BOTTOM_TAB_ROUTES = [
  'home',
  'browse',
  'cart',
  'orders',
  'account',
] as const;

export const TAB_ICON_NAMES = {
  home: { active: 'home', inactive: 'home-outline' },
  search: { active: 'search', inactive: 'search-outline' },
  browse: { active: 'grid', inactive: 'grid-outline' },
  cart: { active: 'cart', inactive: 'cart-outline' },
  orders: { active: 'receipt', inactive: 'receipt-outline' },
  account: { active: 'person', inactive: 'person-outline' },
} as const;

export type TabRouteName = keyof typeof TAB_ICON_NAMES;
export type BottomTabRouteName = (typeof BOTTOM_TAB_ROUTES)[number];

export function resolveTabIconName(
  routeName: string,
  focused: boolean,
): string {
  const icons = TAB_ICON_NAMES[routeName as TabRouteName];
  if (!icons) return focused ? 'ellipse' : 'ellipse-outline';
  return focused ? icons.active : icons.inactive;
}

export function isVisibleBottomTab(routeName: string): boolean {
  return (BOTTOM_TAB_ROUTES as readonly string[]).includes(routeName);
}
