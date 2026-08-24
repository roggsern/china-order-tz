import { Image, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { colors, radius } from '../theme';
import { BRAND_NAME, brandAssetPaths } from './assets';

type Props = {
  /** Visual size of the square mark (splash uses this as a square box). */
  size?: number;
  style?: StyleProp<ViewStyle>;
  variant?: 'mark' | 'header' | 'splash';
};

export function resolveBrandMarkLayout(
  variant: NonNullable<Props['variant']>,
  size: number,
) {
  const isSplash = variant === 'splash';
  return {
    width: variant === 'mark' || isSplash ? size : Math.round(size * 3.2),
    height: size,
    resizeMode: 'contain' as const,
    overflow: isSplash ? ('visible' as const) : ('hidden' as const),
  };
}

/**
 * Official CHINA ORDER TZ logo presentation.
 * Uses checked-in branding assets — no generated artwork.
 */
export function BrandMark({ size = 40, style, variant = 'mark' }: Props) {
  const source =
    variant === 'header'
      ? brandAssetPaths.logoHeader
      : variant === 'splash'
        ? brandAssetPaths.splashBrandSafe
        : brandAssetPaths.logoMark;

  const layout = resolveBrandMarkLayout(variant, size);

  return (
    <View
      style={[
        styles.wrap,
        variant === 'splash' ? styles.splashWrap : null,
        { width: layout.width, height: layout.height },
        style,
      ]}
    >
      <Image
        source={source}
        style={{ width: layout.width, height: layout.height }}
        resizeMode={layout.resizeMode}
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
  splashWrap: {
    borderRadius: 0,
    overflow: 'visible',
    backgroundColor: 'transparent',
  },
});
