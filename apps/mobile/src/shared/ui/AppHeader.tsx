import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import {
  Pressable,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { BRAND_NAME, brandAssetPaths } from '@/src/shared/branding';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { formatTabBadgeCount } from '@/src/shared/navigation/tabBadges';

export type AppHeaderProps = {
  /** Screen title when not showing the brand lockup. */
  title?: string;
  /** Prefer brand mark + name (home / main commerce tabs). */
  showBrand?: boolean;
  showSearch?: boolean;
  showCart?: boolean;
  cartCount?: number | null;
  showBack?: boolean;
  onBackPress?: () => void;
  style?: StyleProp<ViewStyle>;
};

/**
 * Premium storefront header for tabs and stack screens.
 * Brand mark uses official assets from `src/shared/branding`.
 */
export function AppHeader({
  title,
  showBrand = false,
  showSearch = true,
  showCart = true,
  cartCount,
  showBack = false,
  onBackPress,
  style,
}: AppHeaderProps) {
  const insets = useSafeAreaInsets();
  const cartBadge = formatTabBadgeCount(cartCount);

  return (
    <View
      style={[
        styles.wrap,
        { paddingTop: Math.max(insets.top, spacing.sm) },
        style,
      ]}
    >
      <View style={styles.row}>
        <View style={styles.leading}>
          {showBack ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Go back"
              hitSlop={8}
              onPress={() => {
                if (onBackPress) {
                  onBackPress();
                  return;
                }
                if (router.canGoBack()) {
                  router.back();
                } else {
                  router.replace('/(app)/(tabs)/home');
                }
              }}
              style={styles.iconButton}
            >
              <Ionicons name="chevron-back" size={24} color={colors.text} />
            </Pressable>
          ) : null}

          {showBrand ? (
            <View style={styles.brandBlock}>
              <Image
                source={brandAssetPaths.logoMark}
                style={styles.logo}
                contentFit="contain"
                accessibilityLabel={BRAND_NAME}
              />
              <View style={styles.brandTextWrap}>
                <Text style={styles.brandName} numberOfLines={1}>
                  {BRAND_NAME}
                </Text>
                {title ? (
                  <Text style={styles.brandSubtitle} numberOfLines={1}>
                    {title}
                  </Text>
                ) : null}
              </View>
            </View>
          ) : (
            <Text style={styles.title} numberOfLines={1}>
              {title ?? BRAND_NAME}
            </Text>
          )}
        </View>

        <View style={styles.actions}>
          {showSearch ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Search"
              hitSlop={8}
              onPress={() => router.push('/(app)/(tabs)/search')}
              style={styles.iconButton}
            >
              <Ionicons name="search-outline" size={22} color={colors.text} />
            </Pressable>
          ) : null}
          {showCart ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={
                cartBadge ? `Cart, ${cartBadge} items` : 'Cart'
              }
              hitSlop={8}
              onPress={() => router.push('/(app)/(tabs)/cart')}
              style={styles.iconButton}
            >
              <Ionicons name="cart-outline" size={22} color={colors.text} />
              {cartBadge ? (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{cartBadge}</Text>
                </View>
              ) : null}
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: colors.background,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
    paddingBottom: spacing.sm,
    paddingHorizontal: spacing.lg,
  },
  row: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  leading: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    minWidth: 0,
  },
  brandBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    flexShrink: 1,
    minWidth: 0,
  },
  logo: {
    width: 32,
    height: 32,
    borderRadius: radius.md,
    backgroundColor: 'transparent',
  },
  brandTextWrap: {
    flexShrink: 1,
    minWidth: 0,
  },
  brandName: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    fontSize: 13,
    letterSpacing: 0.2,
  },
  brandSubtitle: {
    ...typography.caption,
    marginTop: 1,
  },
  title: {
    ...typography.title,
    fontSize: 17,
    flexShrink: 1,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
  },
  iconButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.full,
  },
  badge: {
    position: 'absolute',
    top: 4,
    right: 2,
    minWidth: 16,
    height: 16,
    paddingHorizontal: 3,
    borderRadius: radius.full,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: colors.onPrimary,
    fontSize: 9,
    fontWeight: '700',
  },
});
