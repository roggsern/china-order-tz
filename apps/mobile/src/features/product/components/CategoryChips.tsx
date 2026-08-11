import { Pressable, ScrollView, StyleSheet, Text } from 'react-native';
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
        style={[styles.chip, selectedSlug == null ? styles.chipActive : null]}
        onPress={() => onSelect(null)}
      >
        <Text style={[styles.chipText, selectedSlug == null ? styles.chipTextActive : null]}>
          All
        </Text>
      </Pressable>
      {categories.map((category) => {
        const active = selectedSlug === category.slug;
        return (
          <Pressable
            key={category.id}
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
    paddingHorizontal: 16,
    paddingBottom: 12,
    gap: 8,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#ccc',
    backgroundColor: '#fff',
  },
  chipActive: {
    borderColor: '#0a7ea4',
    backgroundColor: '#e7f5fa',
  },
  chipText: {
    fontSize: 13,
    color: '#444',
  },
  chipTextActive: {
    color: '#0a7ea4',
    fontWeight: '700',
  },
});
