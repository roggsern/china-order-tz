import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
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

export function SuggestionList({ suggestions, isLoading, onSelect }: Props) {
  if (isLoading && suggestions.length === 0) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator color="#0a7ea4" />
      </View>
    );
  }

  if (suggestions.length === 0) {
    return (
      <Text style={styles.empty}>No suggestions yet. Keep typing or submit to search.</Text>
    );
  }

  return (
    <View style={styles.list}>
      {suggestions.map((suggestion) => (
        <Pressable
          key={suggestion.id}
          style={styles.row}
          onPress={() => onSelect(suggestion)}
        >
          <Text style={styles.label} numberOfLines={1}>
            {suggestion.label}
          </Text>
          <Text style={styles.kind}>{kindLabel(suggestion.kind)}</Text>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  list: {
    paddingHorizontal: 8,
  },
  row: {
    paddingHorizontal: 12,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e5e5e5',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  label: {
    flex: 1,
    fontSize: 15,
    color: '#222',
  },
  kind: {
    fontSize: 11,
    color: '#888',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  empty: {
    paddingHorizontal: 16,
    paddingVertical: 24,
    color: '#666',
    fontSize: 14,
  },
  centered: {
    paddingVertical: 32,
    alignItems: 'center',
  },
});
