import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { PaymentAvailabilityOption } from '../utils/paymentAvailability';

type Props = {
  options: PaymentAvailabilityOption[];
  selectedCode: string | null;
  onSelect: (code: string) => void;
  disabled?: boolean;
};

export function PaymentMethodSelector({
  options,
  selectedCode,
  onSelect,
  disabled,
}: Props) {
  if (options.length === 0) {
    return (
      <Text style={styles.empty}>
        No payment methods are available right now. Please try again later.
      </Text>
    );
  }

  return (
    <View style={styles.list}>
      {options.map((option) => {
        const selected = selectedCode === option.code;
        const actionable = option.supported;
        return (
          <Pressable
            key={option.code}
            accessibilityRole="button"
            accessibilityState={{ selected, disabled: disabled || !actionable }}
            disabled={disabled || !actionable}
            onPress={() => onSelect(option.code)}
            style={[
              styles.option,
              selected && styles.optionSelected,
              !actionable && styles.optionDisabled,
            ]}
          >
            <Text style={styles.label}>{option.label}</Text>
            <Text style={styles.description}>{option.description}</Text>
            {!actionable ? (
              <Text style={styles.unsupported}>
                This payment method is not available yet.
              </Text>
            ) : null}
          </Pressable>
        );
      })}
    </View>
  );
}

export function PaymentMethodSelectorCard(props: Props) {
  return (
    <Card elevated={false} style={styles.card}>
      <Text style={styles.title}>Choose payment method</Text>
      <PaymentMethodSelector {...props} />
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surfaceCream,
    borderColor: colors.primary,
    marginBottom: spacing.md,
  },
  title: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  list: { gap: spacing.sm },
  option: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: colors.background,
  },
  optionSelected: {
    borderColor: colors.primary,
    backgroundColor: colors.backgroundMuted,
  },
  optionDisabled: {
    opacity: 0.7,
  },
  label: {
    ...typography.body,
    fontWeight: '700',
    color: colors.text,
  },
  description: {
    ...typography.caption,
    marginTop: spacing.xs,
  },
  unsupported: {
    ...typography.caption,
    color: colors.textMuted,
    marginTop: spacing.xs,
  },
  empty: {
    ...typography.caption,
    textAlign: 'center',
  },
});
