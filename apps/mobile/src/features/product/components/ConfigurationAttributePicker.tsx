import { Pressable, StyleSheet, Text, View } from 'react-native';
import type { ProductConfigurationAttribute } from '../models/types';

type Props = {
  attribute: ProductConfigurationAttribute;
  selectedValueId?: string | null;
  allowedValueIds: string[];
  onSelect: (valueId: string) => void;
  disabled?: boolean;
};

export function ConfigurationAttributePicker({
  attribute,
  selectedValueId,
  allowedValueIds,
  onSelect,
  disabled,
}: Props) {
  const allowed = new Set(allowedValueIds);

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{attribute.name}</Text>
      <View style={styles.row}>
        {attribute.values.map((value) => {
          const isAllowed = allowed.has(value.id);
          const isSelected = selectedValueId === value.id;
          const isDisabled = disabled || !isAllowed;
          return (
            <Pressable
              key={value.id}
              style={[
                styles.chip,
                isSelected ? styles.chipSelected : null,
                isDisabled ? styles.chipDisabled : null,
              ]}
              disabled={isDisabled}
              onPress={() => onSelect(value.id)}
            >
              <Text
                style={[
                  styles.chipText,
                  isSelected ? styles.chipTextSelected : null,
                  isDisabled ? styles.chipTextDisabled : null,
                ]}
              >
                {value.value}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 14,
  },
  label: {
    fontSize: 14,
    fontWeight: '700',
    color: '#222',
    marginBottom: 8,
  },
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: '#fff',
  },
  chipSelected: {
    borderColor: '#0a7ea4',
    backgroundColor: '#e7f5fa',
  },
  chipDisabled: {
    opacity: 0.35,
  },
  chipText: {
    fontSize: 13,
    color: '#333',
    fontWeight: '500',
  },
  chipTextSelected: {
    color: '#0a7ea4',
    fontWeight: '700',
  },
  chipTextDisabled: {
    color: '#888',
  },
});
