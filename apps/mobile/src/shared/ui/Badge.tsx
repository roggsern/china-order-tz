import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, radius, spacing, typography } from '../theme';

export type BadgeTone = 'brand' | 'neutral' | 'success' | 'error' | 'warning' | 'info';

type Props = {
  label: string;
  tone?: BadgeTone;
  style?: StyleProp<ViewStyle>;
};

const toneStyles: Record<
  BadgeTone,
  { backgroundColor: string; color: string; borderColor: string }
> = {
  brand: {
    backgroundColor: colors.primaryMuted,
    color: colors.primaryPressed,
    borderColor: colors.primary,
  },
  neutral: {
    backgroundColor: colors.backgroundMuted,
    color: colors.textMuted,
    borderColor: colors.border,
  },
  success: {
    backgroundColor: colors.successMuted,
    color: colors.success,
    borderColor: colors.success,
  },
  error: {
    backgroundColor: colors.errorMuted,
    color: colors.error,
    borderColor: colors.error,
  },
  warning: {
    backgroundColor: colors.warningMuted,
    color: colors.warning,
    borderColor: colors.warning,
  },
  info: {
    backgroundColor: colors.infoMuted,
    color: colors.info,
    borderColor: colors.info,
  },
};

export function Badge({ label, tone = 'brand', style }: Props) {
  const toneStyle = toneStyles[tone];
  return (
    <View
      style={[
        styles.base,
        {
          backgroundColor: toneStyle.backgroundColor,
          borderColor: toneStyle.borderColor,
        },
        style,
      ]}
    >
      <Text style={[styles.label, { color: toneStyle.color }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    alignSelf: 'flex-start',
    borderRadius: radius.full,
    borderWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xxs + 1,
  },
  label: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
});
