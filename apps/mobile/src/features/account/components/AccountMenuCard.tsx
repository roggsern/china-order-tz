import { StyleSheet, Text, View, Pressable } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { Badge } from '@/src/shared/ui/Badge';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  title: string;
  description: string;
  badge?: string;
  onPress: () => void;
  /** Visually de-emphasize website handoffs vs native capabilities. */
  secondary?: boolean;
};

/** Premium menu row for the account hub. */
export function AccountMenuCard({
  title,
  description,
  badge,
  onPress,
  secondary = false,
}: Props) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [pressed ? styles.pressed : null]}
    >
      <Card
        elevated={!secondary}
        style={[styles.card, secondary ? styles.secondaryCard : null]}
      >
        <View style={styles.row}>
          <View style={styles.copy}>
            <Text style={[styles.title, secondary ? styles.secondaryTitle : null]}>
              {title}
            </Text>
            <Text
              style={[
                styles.description,
                secondary ? styles.secondaryDescription : null,
              ]}
            >
              {description}
            </Text>
          </View>
          {badge ? <Badge label={badge} tone="neutral" /> : null}
        </View>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressed: { opacity: 0.92 },
  card: {
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  secondaryCard: {
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
    opacity: 0.92,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  copy: { flex: 1 },
  title: {
    ...typography.bodyStrong,
    color: colors.text,
  },
  secondaryTitle: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  description: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
  secondaryDescription: {
    color: colors.textMuted,
  },
});
