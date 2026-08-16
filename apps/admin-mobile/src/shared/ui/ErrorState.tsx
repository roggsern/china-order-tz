import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ApiError } from '@/src/core/api';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

type ErrorStateProps = {
  error: unknown;
  onRetry?: () => void;
};

function resolveMessage(error: unknown): { title: string; message: string } {
  if (error instanceof ApiError) {
    if (error.isUnauthenticated) {
      return { title: 'Session expired', message: 'Please sign in again.' };
    }
    if (error.isForbidden) {
      return { title: 'Access denied', message: error.message || 'You do not have permission.' };
    }
    if (error.isNetworkFailure) {
      return { title: 'Connection problem', message: error.message };
    }
    return { title: 'Something went wrong', message: error.message };
  }

  return {
    title: 'Something went wrong',
    message: error instanceof Error ? error.message : 'Unexpected error',
  };
}

export function ErrorState({ error, onRetry }: ErrorStateProps) {
  const { title, message } = resolveMessage(error);

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry ? (
        <Pressable style={styles.button} onPress={onRetry}>
          <Text style={styles.buttonText}>Try again</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
    backgroundColor: colors.background,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.danger,
    textAlign: 'center',
  },
  message: {
    marginTop: spacing.sm,
    fontSize: 14,
    color: colors.textMuted,
    textAlign: 'center',
  },
  button: {
    marginTop: spacing.lg,
    backgroundColor: colors.navy,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    borderRadius: radii.md,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
