import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

type Props = {
  enabled: boolean;
  submitting: boolean;
  onPress: () => void;
  label?: string;
};

export function AddToCartButton({
  enabled,
  submitting,
  onPress,
  label = 'Add to cart',
}: Props) {
  const disabled = !enabled || submitting;

  return (
    <View style={styles.wrap}>
      <Pressable
        style={[styles.button, disabled ? styles.buttonDisabled : null]}
        disabled={disabled}
        onPress={onPress}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>{label}</Text>
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 20,
  },
  button: {
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonDisabled: {
    backgroundColor: '#9bbdca',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
});
