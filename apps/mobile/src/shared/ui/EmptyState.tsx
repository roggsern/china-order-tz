import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, spacing, typography } from '../theme';
import { PrimaryButton } from './PrimaryButton';
import { SecondaryButton } from './SecondaryButton';

type Props = {
  title: string;
  message?: string;
  actionLabel?: string;
  onActionPress?: () => void;
  secondaryLabel?: string;
  onSecondaryPress?: () => void;
  style?: StyleProp<ViewStyle>;
};

export function EmptyState({
  title,
  message,
  actionLabel,
  onActionPress,
  secondaryLabel,
  onSecondaryPress,
  style,
}: Props) {
  return (
    <View style={[styles.wrap, style]}>
      <Text style={styles.title}>{title}</Text>
      {message ? <Text style={styles.message}>{message}</Text> : null}
      {actionLabel && onActionPress ? (
        <PrimaryButton
          label={actionLabel}
          onPress={onActionPress}
          style={styles.action}
        />
      ) : null}
      {secondaryLabel && onSecondaryPress ? (
        <SecondaryButton
          label={secondaryLabel}
          onPress={onSecondaryPress}
          style={styles.secondary}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xxl,
    gap: spacing.sm,
    backgroundColor: colors.background,
  },
  title: {
    ...typography.title,
    textAlign: 'center',
  },
  message: {
    ...typography.body,
    textAlign: 'center',
    marginBottom: spacing.sm,
  },
  action: {
    minWidth: 180,
    marginTop: spacing.sm,
  },
  secondary: {
    minWidth: 180,
  },
});
