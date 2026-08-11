import { StyleSheet, View } from 'react-native';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, spacing } from '@/src/shared/theme';

type Props = {
  enabled: boolean;
  submitting: boolean;
  onPress: () => void;
  label?: string;
};

/** Premium purchase CTA — presentation only; callers own cart logic. */
export function AddToCartButton({
  enabled,
  submitting,
  onPress,
  label = 'Add to cart',
}: Props) {
  return (
    <View style={styles.wrap}>
      <PrimaryButton
        label={label}
        loading={submitting}
        disabled={!enabled}
        onPress={onPress}
        style={styles.button}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.xl,
    paddingTop: spacing.lg,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  button: {
    alignSelf: 'stretch',
  },
});
