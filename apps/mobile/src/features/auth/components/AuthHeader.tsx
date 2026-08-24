import { StyleSheet, Text, View } from 'react-native';
import { BrandMark, BRAND_NAME } from '@/src/shared/branding';
import { colors, spacing, typography } from '@/src/shared/theme';

export const AUTH_BRAND_VARIANT = 'mark' as const;
export const AUTH_BRAND_SIZE = 40;

type Props = {
  title: string;
  subtitle: string;
};

/**
 * Compact in-app brand + heading. Uses the header/mark asset, never the
 * splash-safe square lockup (oversized and crop-prone on auth pages).
 */
export function AuthHeader({ title, subtitle }: Props) {
  return (
    <View style={styles.wrap}>
      <BrandMark
        variant={AUTH_BRAND_VARIANT}
        size={AUTH_BRAND_SIZE}
        style={styles.mark}
      />
      <Text style={styles.brand} accessibilityRole="header">
        {BRAND_NAME}
      </Text>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.subtitle}>{subtitle}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: spacing.xl,
  },
  mark: {
    overflow: 'visible',
    backgroundColor: 'transparent',
    borderRadius: 0,
    marginBottom: spacing.md,
  },
  brand: {
    ...typography.label,
    color: colors.text,
    letterSpacing: 0.8,
    marginBottom: spacing.lg,
  },
  title: {
    ...typography.heading,
    color: colors.text,
    marginBottom: spacing.sm,
  },
  subtitle: {
    ...typography.body,
    color: colors.textSecondary,
  },
});
