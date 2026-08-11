import { StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, radius, spacing } from '../theme';

type Props = {
  /** Approximate height of the placeholder block. */
  height?: number;
  width?: number | `${number}%`;
  borderRadius?: number;
  style?: StyleProp<ViewStyle>;
};

/**
 * Static skeleton placeholder — no animation dependency.
 * Screens can compose multiple rows for list loading.
 */
export function LoadingSkeleton({
  height = 16,
  width = '100%',
  borderRadius = radius.md,
  style,
}: Props) {
  return (
    <View
      accessibilityLabel="Loading"
      style={[
        styles.base,
        { height, width, borderRadius },
        style,
      ]}
    />
  );
}

type BlockProps = {
  lines?: number;
  style?: StyleProp<ViewStyle>;
};

export function LoadingSkeletonBlock({ lines = 3, style }: BlockProps) {
  return (
    <View style={[styles.block, style]}>
      {Array.from({ length: lines }).map((_, index) => (
        <LoadingSkeleton
          key={`sk-${index}`}
          width={index === lines - 1 ? '70%' : '100%'}
          style={styles.line}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    backgroundColor: colors.skeleton,
  },
  block: {
    gap: spacing.sm,
    padding: spacing.lg,
  },
  line: {
    marginBottom: 0,
  },
});
