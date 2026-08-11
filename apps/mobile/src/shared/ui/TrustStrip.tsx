import { StyleSheet, Text, View } from 'react-native';
import { Card } from './Card';
import { colors, spacing, typography } from '../theme';

export type TrustStripItem = {
  id: string;
  title: string;
  description?: string | null;
};

type Props = {
  items: TrustStripItem[];
  title?: string;
};

/**
 * Compact trust / value row for PDP and merchandising surfaces.
 * Callers supply items — this component never invents commerce data.
 */
export function TrustStrip({ items, title = 'Shopping with confidence' }: Props) {
  if (items.length === 0) return null;

  return (
    <View style={styles.wrap} accessibilityRole="summary">
      <Text style={styles.title}>{title}</Text>
      <View style={styles.list}>
        {items.map((item) => (
          <Card key={item.id} style={styles.card} elevated={false}>
            <Text style={styles.itemTitle}>{item.title}</Text>
            {item.description ? (
              <Text style={styles.itemBody}>{item.description}</Text>
            ) : null}
          </Card>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.xxl,
    gap: spacing.sm,
  },
  title: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.7,
    color: colors.primaryPressed,
  },
  list: {
    gap: spacing.sm,
  },
  card: {
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
  },
  itemTitle: {
    ...typography.bodyStrong,
  },
  itemBody: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
});
