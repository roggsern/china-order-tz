import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

type Props = {
  quantity: number;
  onChange: (quantity: number) => void;
  min?: number;
  /** Available sellable stock. Required so callers cannot silently default to 99. */
  max: number;
  disabled?: boolean;
};

export function QuantitySelector({
  quantity,
  onChange,
  min = 1,
  max,
  disabled,
}: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Quantity</Text>
      <View style={styles.controls}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Decrease quantity"
          style={[styles.button, disabled ? styles.disabled : null]}
          disabled={disabled || quantity <= min}
          onPress={() => onChange(Math.max(min, quantity - 1))}
        >
          <Text style={styles.buttonText}>−</Text>
        </Pressable>
        <Text style={styles.value} accessibilityLabel={`Quantity ${quantity}`}>
          {quantity}
        </Text>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Increase quantity"
          style={[styles.button, disabled ? styles.disabled : null]}
          disabled={disabled || quantity >= max}
          onPress={() => onChange(Math.min(max, quantity + 1))}
        >
          <Text style={styles.buttonText}>+</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.lg,
  },
  label: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  controls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.lg,
  },
  button: {
    width: 44,
    height: 44,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
  },
  disabled: {
    opacity: 0.4,
  },
  buttonText: {
    ...typography.title,
    color: colors.text,
  },
  value: {
    minWidth: 32,
    textAlign: 'center',
    ...typography.bodyStrong,
    fontSize: 18,
  },
});
