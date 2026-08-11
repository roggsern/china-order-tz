import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { ProductConfigurationAttributeValue } from '../models/types';

type Props = {
  attributeName: string;
  values: ProductConfigurationAttributeValue[];
  selectedValueId?: string | null;
  allowedValueIds: string[];
  /** Toggle: select, replace, or clear when already selected. */
  onToggle: (valueId: string) => void;
  disabled?: boolean;
};

/**
 * Attribute chips — only product-assigned values (caller filters).
 * Cascade: allowed_value_ids enable/disable. Selected-but-disallowed stays pressable to clear.
 */
export function ConfigurationAttributePicker({
  attributeName,
  values,
  selectedValueId,
  allowedValueIds,
  onToggle,
  disabled,
}: Props) {
  const allowed = new Set(allowedValueIds);

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{attributeName}</Text>
      <View style={styles.row}>
        {values.map((value) => {
          const isAllowed = allowed.has(value.id);
          const isSelected = selectedValueId === value.id;
          // Web: disabled = !enabled && !isSelected — selected stays tappable to deselect.
          const isDisabled = Boolean(disabled) || (!isAllowed && !isSelected);
          return (
            <Pressable
              key={value.id}
              accessibilityRole="button"
              accessibilityState={{ selected: isSelected, disabled: isDisabled }}
              style={[
                styles.chip,
                isSelected ? styles.chipSelected : null,
                !isAllowed && !isSelected ? styles.chipDisabled : null,
              ]}
              disabled={isDisabled}
              onPress={() => onToggle(value.id)}
            >
              <Text
                style={[
                  styles.chipText,
                  isSelected ? styles.chipTextSelected : null,
                  !isAllowed && !isSelected ? styles.chipTextDisabled : null,
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
