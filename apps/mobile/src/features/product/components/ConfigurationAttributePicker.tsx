import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
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
              accessibilityRole="button"
              accessibilityState={{ selected: isSelected, disabled: isDisabled }}
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
    marginBottom: spacing.md,
  },
  label: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: colors.surface,
  },
  chipSelected: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryMuted,
  },
  chipDisabled: {
    opacity: 0.35,
  },
  chipText: {
    ...typography.label,
    color: colors.textSecondary,
  },
  chipTextSelected: {
    color: colors.primaryPressed,
    fontWeight: '700',
  },
  chipTextDisabled: {
    color: colors.textSubtle,
  },
});
