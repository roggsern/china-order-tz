import { StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { colors, spacing, typography } from '../theme';
import { PrimaryButton, SecondaryButton } from '../ui';

type Props = {
  title: string;
  message: string;
  primaryLabel?: string;
  primaryHref?: string;
  secondaryLabel?: string;
  onSecondaryPress?: () => void;
};

/**
 * Customer-facing empty state when a required route id/context is missing.
 */
export function MissingRouteState({
  title,
  message,
  primaryLabel = 'Go to Home',
  primaryHref = '/(app)/(tabs)/home',
  secondaryLabel = 'Back',
  onSecondaryPress,
}: Props) {
  return (
    <View style={styles.centered}>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.body}>{message}</Text>
      <PrimaryButton
        label={primaryLabel}
        onPress={() => router.replace(primaryHref as never)}
        style={styles.button}
      />
      <SecondaryButton
        label={secondaryLabel}
        onPress={() => {
          if (onSecondaryPress) {
            onSecondaryPress();
            return;
          }
          if (router.canGoBack()) {
            router.back();
          } else {
            router.replace(primaryHref as never);
          }
        }}
        style={styles.button}
      />
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
    gap: spacing.sm,
  },
  title: {
    ...typography.title,
    textAlign: 'center',
  },
  body: {
    ...typography.body,
    textAlign: 'center',
    marginBottom: spacing.sm,
  },
  button: {
    minWidth: 180,
  },
});
