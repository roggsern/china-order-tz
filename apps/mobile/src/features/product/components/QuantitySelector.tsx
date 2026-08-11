import { Pressable, StyleSheet, Text, View } from 'react-native';

type Props = {
  quantity: number;
  onChange: (quantity: number) => void;
  min?: number;
  max?: number;
  disabled?: boolean;
};

export function QuantitySelector({
  quantity,
  onChange,
  min = 1,
  max = 99,
  disabled,
}: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Quantity</Text>
      <View style={styles.controls}>
        <Pressable
          style={[styles.button, disabled ? styles.disabled : null]}
          disabled={disabled || quantity <= min}
          onPress={() => onChange(Math.max(min, quantity - 1))}
        >
          <Text style={styles.buttonText}>−</Text>
        </Pressable>
        <Text style={styles.value}>{quantity}</Text>
        <Pressable
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
    marginTop: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 8,
  },
  controls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  button: {
    width: 40,
    height: 40,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ccc',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  disabled: {
    opacity: 0.4,
  },
  buttonText: {
    fontSize: 20,
    fontWeight: '600',
    color: '#222',
  },
  value: {
    minWidth: 28,
    textAlign: 'center',
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
  },
});
