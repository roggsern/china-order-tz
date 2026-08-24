import { type ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import { colors, radius, shadows, spacing } from '@/src/shared/theme';

type Props = {
  children: ReactNode;
};

export function AuthCard({ children }: Props) {
  return <View style={styles.card}>{children}</View>;
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.xxl,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.xl,
    ...shadows.sm,
  },
});
