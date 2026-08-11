import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { SearchSuggestion } from '../models/types';

type Props = {
  suggestions: SearchSuggestion[];
  isLoading?: boolean;
  onSelect: (suggestion: SearchSuggestion) => void;
};

function kindLabel(kind: SearchSuggestion['kind']): string {
  switch (kind) {
    case 'brand':
      return 'Brand';
    case 'store':
      return 'Store';
    case 'category':
      return 'Category';
    default:
      return 'Product';
  }
}

function kindTone(
  kind: SearchSuggestion['kind'],
): 'brand' | 'neutral' | 'success' | 'info' {
  switch (kind) {
    case 'brand':
      return 'info';
    case 'store':
      return 'success';
    case 'category':
      return 'neutral';
    default:
      return 'brand';
  }
}

export function SuggestionList({ suggestions, isLoading, onSelect }: Props) {
  if (isLoading && suggestions.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator color={colors.primary} />
        <Text style={styles.loadingLabel}>Looking up suggestions…</Text>
      </View>
    );
  }

  if (suggestions.length === 0) {
    return (
      <Text style={styles.empty}>
        No suggestions yet. Keep typing or submit to search.
      </Text>
    );
  }

  return (
    <View style={styles.list}>
      {suggestions.map((suggestion) => (
        <Pressable
          key={suggestion.id}
          style={styles.row}
          onPress={() => onSelect(suggestion)}
          accessibilityRole="button"
        >
          <Text style={styles.label} numberOfLines={1}>
            {suggestion.label}
          </Text>
          <Badge label={kindLabel(suggestion.kind)} tone={kindTone(suggestion.kind)} />
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  list: {
    paddingHorizontal: spacing.sm,
  },
  row: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  label: {
    flex: 1,
    ...typography.bodyStrong,
  },
  empty: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xxl,
    ...typography.body,
  },
  centered: {
    paddingVertical: spacing.xxxl,
    alignItems: 'center',
    gap: spacing.sm,
  },
  loadingLabel: {
    ...typography.caption,
  },
});
