import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { Badge } from '@/src/shared/ui/Badge';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  title: string;
  description: string;
  badge?: string;
  onPress: () => void;
};

/** Premium menu row for the account hub. */
export function AccountMenuCard({ title, description, badge, onPress }: Props) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [pressed ? styles.pressed : null]}
    >
      <Card elevated style={styles.card}>
        <View style={styles.row}>
          <View style={styles.copy}>
            <Text style={styles.title}>{title}</Text>
            <Text style={styles.description}>{description}</Text>
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
  description: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
});
