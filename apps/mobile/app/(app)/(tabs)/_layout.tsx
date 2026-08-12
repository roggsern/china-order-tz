import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { StyleSheet, type ColorValue } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useCart } from '@/src/features/cart/hooks/useCart';
import { resolveTabIconName } from '@/src/shared/navigation/tabIcons';
import { useShellTabBadges } from '@/src/shared/navigation/useShellTabBadges';
import { colors, spacing } from '@/src/shared/theme';
import { AppHeader } from '@/src/shared/ui/AppHeader';

function TabBarIcon({
  routeName,
  color,
  size,
  focused,
}: {
  routeName: string;
  color: ColorValue;
  size: number;
  focused: boolean;
}) {
  return (
    <Ionicons
      name={resolveTabIconName(routeName, focused) as keyof typeof Ionicons.glyphMap}
      size={size}
      color={color}
    />
  );
}

function BrandTabHeader({ title }: { title: string }) {
  const cartQuery = useCart();
  return (
    <AppHeader
      showBrand
      title={title}
      showSearch
      showCart
      cartCount={cartQuery.data?.itemCount ?? 0}
    />
  );
}

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const { cartBadge, ordersBadge, accountBadge } = useShellTabBadges();

  return (
    <Tabs
      screenOptions={({ route }) => ({
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarLabelStyle: styles.tabLabel,
        tabBarStyle: [
          styles.tabBar,
          {
            paddingBottom: Math.max(insets.bottom, spacing.sm),
            height: 56 + Math.max(insets.bottom, spacing.sm),
          },
        ],
        tabBarItemStyle: styles.tabItem,
        tabBarIcon: ({ color, size, focused }) => (
          <TabBarIcon
            routeName={route.name}
            color={color}
            size={size}
            focused={focused}
          />
        ),
      })}
    >
      <Tabs.Screen
        name="home"
        options={{
          title: 'Home',
          header: () => <BrandTabHeader title="Home" />,
        }}
      />
      <Tabs.Screen
        name="search"
        options={{
          title: 'Search',
          href: null,
          header: () => <BrandTabHeader title="Search" />,
        }}
      />
      <Tabs.Screen
        name="browse"
        options={{
          title: 'Shop',
          header: () => <BrandTabHeader title="Shop" />,
        }}
      />
      <Tabs.Screen
        name="cart"
        options={{
          title: 'Cart',
          header: () => <BrandTabHeader title="Cart" />,
          tabBarBadge: cartBadge,
          tabBarBadgeStyle: styles.nativeBadge,
        }}
      />
      <Tabs.Screen
        name="orders"
        options={{
          title: 'Orders',
          header: () => <BrandTabHeader title="Orders" />,
          tabBarBadge: ordersBadge,
          tabBarBadgeStyle: styles.nativeBadge,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: 'Account',
          header: () => <BrandTabHeader title="Account" />,
          tabBarBadge: accountBadge,
          tabBarBadgeStyle: styles.nativeBadge,
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: colors.background,
    borderTopColor: colors.border,
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingTop: spacing.xs,
  },
  tabItem: {
    paddingTop: spacing.xxs,
  },
  tabLabel: {
    fontSize: 11,
    fontWeight: '600',
    letterSpacing: 0.1,
  },
  nativeBadge: {
    backgroundColor: colors.primary,
    color: colors.onPrimary,
    fontSize: 10,
    fontWeight: '700',
  },
});
