import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { BRAND_NAME, BRAND_TAGLINE, BrandMark } from '@/src/shared/branding';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  message?: string;
};

/** Branded splash while SecureStore + GET /me bootstrap runs. */
export function SplashView({ message = 'Restoring session…' }: Props) {
  return (
    <View style={styles.container} accessibilityLabel="splash">
      <View style={styles.hero}>
        <BrandMark variant="splash" size={56} style={styles.logo} />
        <Text style={styles.title}>{BRAND_NAME}</Text>
        <Text style={styles.tagline}>{BRAND_TAGLINE}</Text>
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
  title: {
    ...typography.heading,
    textAlign: 'center',
    color: colors.text,
    letterSpacing: 0.6,
  },
  tagline: {
    ...typography.caption,
    textAlign: 'center',
    marginTop: spacing.sm,
    color: colors.textMuted,
    maxWidth: 280,
  },
  spinner: {
    marginBottom: spacing.lg,
  },
  message: {
    ...typography.body,
    textAlign: 'center',
  },
});
