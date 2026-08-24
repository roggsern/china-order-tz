import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

type Props = {
  message: string;
};

export function AuthErrorBanner({ message }: Props) {
  return (
    <View style={styles.banner} accessibilityRole="alert">
      <Text style={styles.text}>{message}</Text>
    </View>
  );
}

export function AuthSuccessBanner({
  message,
  hint,
}: {
  message: string;
  hint?: string;
}) {
  return (
    <View
      style={styles.success}
      accessibilityRole="text"
      accessibilityLiveRegion="polite"
    >
      <Text style={styles.successText}>{message}</Text>
      {hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    backgroundColor: colors.errorMuted,
    borderRadius: radius.lg,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  text: {
    ...typography.body,
    color: colors.error,
  },
  success: {
    backgroundColor: colors.successMuted,
    borderRadius: radius.lg,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  successText: {
    ...typography.bodyStrong,
    color: colors.success,
  },
  hint: {
    ...typography.caption,
    marginTop: spacing.sm,
    color: colors.textSecondary,
  },
});
