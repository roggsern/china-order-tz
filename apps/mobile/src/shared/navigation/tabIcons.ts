/**
 * Release-safe tab icon names for @expo/vector-icons Ionicons.
 * Kept free of React Native imports for unit testing.
 */
export const TAB_ICON_NAMES = {
  home: { active: 'home', inactive: 'home-outline' },
  search: { active: 'search', inactive: 'search-outline' },
  browse: { active: 'grid', inactive: 'grid-outline' },
  cart: { active: 'cart', inactive: 'cart-outline' },
  orders: { active: 'receipt', inactive: 'receipt-outline' },
  account: { active: 'person', inactive: 'person-outline' },
} as const;

export type TabRouteName = keyof typeof TAB_ICON_NAMES;

export function resolveTabIconName(
  routeName: string,
  focused: boolean,
): string {
  const icons = TAB_ICON_NAMES[routeName as TabRouteName];
  if (!icons) return focused ? 'ellipse' : 'ellipse-outline';
  return focused ? icons.active : icons.inactive;
}
