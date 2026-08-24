import { StyleSheet, Text } from 'react-native';
import { openAccountWebPage } from '@/src/features/account/utils/accountWebLinks';
import { colors, spacing, typography } from '@/src/shared/theme';

/**
 * Informational legal handoff only — not a required checkbox and not part of
 * the register payload.
 */
export function AuthLegalNote() {
  return (
    <Text style={styles.copy}>
      By creating an account you agree to our{' '}
      <Text
        style={styles.link}
        accessibilityRole="link"
        onPress={() => {
          void openAccountWebPage('/terms').catch(() => undefined);
        }}
      >
        Terms
      </Text>{' '}
      and{' '}
      <Text
        style={styles.link}
        accessibilityRole="link"
        onPress={() => {
          void openAccountWebPage('/privacy').catch(() => undefined);
        }}
      >
        Privacy Policy
      </Text>
      .
    </Text>
  );
}

const styles = StyleSheet.create({
  copy: {
    ...typography.caption,
    textAlign: 'center',
    marginTop: spacing.lg,
    color: colors.textMuted,
  },
  link: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '600',
  },
});
