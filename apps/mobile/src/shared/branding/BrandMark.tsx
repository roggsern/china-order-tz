import { Image, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, radius } from '../theme';
import { BRAND_NAME, brandAssetPaths } from './assets';

type Props = {
  /** Visual size of the square mark. */
  size?: number;
  style?: StyleProp<ViewStyle>;
  variant?: 'mark' | 'header' | 'splash';
};

/**
 * Official CHINA ORDER TZ logo presentation.
 * Uses checked-in branding assets — no generated artwork.
 */
export function BrandMark({ size = 40, style, variant = 'mark' }: Props) {
  const source =
    variant === 'header'
      ? brandAssetPaths.logoHeader
      : variant === 'splash'
        ? brandAssetPaths.splashBrand
        : brandAssetPaths.logoMark;

  const width = variant === 'mark' ? size : Math.round(size * 3.2);
  const height = size;

  return (
    <View style={[styles.wrap, { width, height }, style]}>
      <Image
        source={source}
        style={{ width, height }}
        resizeMode="contain"
        accessibilityLabel={BRAND_NAME}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    borderRadius: radius.md,
    overflow: 'hidden',
    backgroundColor: colors.background,
  },
});
