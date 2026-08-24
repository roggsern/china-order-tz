import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { BrandMark } from '@/src/shared/branding';
import { SPLASH_VIEW_MARK_SIZE } from '@/src/shared/branding/splashPresentation';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  message?: string;
};

/** Branded splash while SecureStore + GET /me bootstrap runs. */
export function SplashView({ message = 'Loading your account…' }: Props) {
  return (
    <View style={styles.container} accessibilityLabel="splash">
      <View style={styles.hero}>
        <BrandMark variant="splash" size={SPLASH_VIEW_MARK_SIZE} style={styles.logo} />
      </View>
      <ActivityIndicator size="large" color={colors.primary} style={styles.spinner} />
      <Text style={styles.message}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xxl,
    backgroundColor: colors.surfaceCream,
  },
  hero: {
    alignItems: 'center',
    marginBottom: spacing.xxxl,
    paddingHorizontal: spacing.lg,
  },
  logo: {
    marginBottom: spacing.lg,
    backgroundColor: 'transparent',
  },
  spinner: {
    marginBottom: spacing.lg,
  },
  message: {
    ...typography.body,
    textAlign: 'center',
  },
});
