import { Pressable, ScrollView, StyleSheet, Text } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { CatalogCategory } from '../models/types';

type Props = {
  categories: CatalogCategory[];
  selectedSlug: string | null;
  onSelect: (slug: string | null) => void;
};

export function CategoryChips({ categories, selectedSlug, onSelect }: Props) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.row}
    >
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ selected: selectedSlug == null }}
        style={[styles.chip, selectedSlug == null ? styles.chipActive : null]}
        onPress={() => onSelect(null)}
      >
        <Text
          style={[
            styles.chipText,
            selectedSlug == null ? styles.chipTextActive : null,
          ]}
        >
          All
        </Text>
      </Pressable>
      {categories.map((category) => {
        const active = selectedSlug === category.slug;
        return (
          <Pressable
            key={category.id}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            style={[styles.chip, active ? styles.chipActive : null]}
            onPress={() => onSelect(category.slug)}
          >
            <Text style={[styles.chipText, active ? styles.chipTextActive : null]}>
              {category.name}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: {
    paddingBottom: spacing.md,
    gap: spacing.sm,
  },
  chip: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  chipActive: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryMuted,
  },
  chipText: {
    ...typography.label,
    color: colors.textSecondary,
  },
  chipTextActive: {
    color: colors.primaryPressed,
    fontWeight: '700',
  },
});
