import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { BrandMark } from '../branding';
import { colors, spacing, typography } from '../theme';

type Props = {
  label?: string;
  /** Show compact brand mark above the spinner (launch / shell loads). */
  showBrand?: boolean;
};

/** Shared full-screen loading state for shell / feature screens. */
export function ScreenLoadingState({
  label = 'Loading…',
  showBrand = false,
}: Props) {
  return (
    <View style={styles.centered} accessibilityLabel={label}>
      {showBrand ? <BrandMark size={36} style={styles.mark} /> : null}
      <ActivityIndicator size="large" color={colors.primary} />
      <Text style={styles.label}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xxl,
    backgroundColor: colors.background,
    gap: spacing.md,
  },
  mark: {
    marginBottom: spacing.sm,
    backgroundColor: 'transparent',
  },
  label: {
    ...typography.body,
    textAlign: 'center',
  },
});
