import { useState } from 'react';
import {
  Dimensions,
  NativeScrollEvent,
  NativeSyntheticEvent,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { CatalogImage } from '../models/types';

type Props = {
  images: CatalogImage[];
};

const SCREEN_WIDTH = Dimensions.get('window').width;
const GALLERY_HEIGHT = 320;

export function ProductImageGallery({ images }: Props) {
  const [index, setIndex] = useState(0);
  const validImages = images.filter((image) => Boolean(image.url));

  function onScrollEnd(event: NativeSyntheticEvent<NativeScrollEvent>) {
    const next = Math.round(event.nativeEvent.contentOffset.x / SCREEN_WIDTH);
    setIndex(Math.max(0, Math.min(next, validImages.length - 1)));
  }

  if (validImages.length === 0) {
    return (
      <View
        style={[styles.slide, styles.placeholder]}
        accessibilityLabel="No product image"
      >
        <Text style={styles.placeholderEyebrow}>CHINA ORDER TZ</Text>
        <Text style={styles.placeholderText}>No product image available</Text>
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <ScrollView
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={onScrollEnd}
        decelerationRate="fast"
      >
        {validImages.map((image, imageIndex) => (
          <Image
            key={image.id ?? `${image.url}-${imageIndex}`}
            source={{ uri: image.url ?? undefined }}
            style={styles.image}
            contentFit="cover"
            transition={200}
            accessibilityLabel={image.altText ?? 'Product image'}
          />
        ))}
      </ScrollView>
      {validImages.length > 1 ? (
        <View style={styles.dots}>
          {validImages.map((image, i) => (
            <View
              key={image.id ?? `dot-${i}`}
              style={[styles.dot, i === index ? styles.dotActive : null]}
            />
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: colors.backgroundMuted,
  },
  slide: {
    width: SCREEN_WIDTH,
    height: GALLERY_HEIGHT,
  },
  image: {
    width: SCREEN_WIDTH,
    height: GALLERY_HEIGHT,
    backgroundColor: colors.backgroundMuted,
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primaryMuted,
    gap: spacing.sm,
  },
  placeholderEyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    letterSpacing: 1,
  },
  placeholderText: {
    ...typography.body,
    color: colors.textMuted,
  },
  dots: {
    position: 'absolute',
    bottom: spacing.md,
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.xs,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: radius.full,
    backgroundColor: 'rgba(255,255,255,0.55)',
  },
  dotActive: {
    width: 16,
    backgroundColor: colors.primary,
  },
});
