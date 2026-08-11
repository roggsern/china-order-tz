import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { HomepageTrustItem } from '../models/types';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  items: HomepageTrustItem[];
};

/** Trust / why-choose block — CMS title, subtitle, and items only. */
export function TrustSection({ title, subtitle, items }: Props) {
  if (!title && !subtitle && items.length === 0) return null;

  const headerTitle = title?.trim() || subtitle?.trim() || null;
  const headerSubtitle = title?.trim() ? subtitle : null;

  return (
    <View style={styles.section}>
      {headerTitle ? (
        <SectionHeader title={headerTitle} subtitle={headerSubtitle} />
      ) : null}
      {items.length > 0 ? (
        <View style={styles.list}>
          {items.map((item) => (
            <Card key={item.id} style={styles.card}>
              <Text style={styles.itemTitle}>{item.title}</Text>
              {item.description ? (
                <Text style={styles.body}>{item.description}</Text>
              ) : null}
            </Card>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: spacing.xxl },
  list: {
    paddingHorizontal: spacing.lg,
    gap: spacing.sm,
  },
  card: {
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  itemTitle: {
    ...typography.bodyStrong,
    marginBottom: spacing.xs,
  },
  body: {
    ...typography.body,
  },
});
