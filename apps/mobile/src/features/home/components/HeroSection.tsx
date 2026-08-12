import { useRef, useState } from 'react';
import {
  Dimensions,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { brandColors, colors, radius, spacing, typography } from '@/src/shared/theme';
import type { HomepageHeroSlide } from '../models/types';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  slides: HomepageHeroSlide[];
};

const SCREEN_WIDTH = Dimensions.get('window').width;
const HERO_HEIGHT = 260;

function slideImageUrl(slide: HomepageHeroSlide): string | null {
  return slide.mobile_media?.url ?? slide.desktop_media?.url ?? null;
}

function journeyFallbackStyle(slideId: string) {
  if (slideId.includes('tz')) {
    return styles.imageFallbackTz;
  }
  return styles.imageFallbackChina;
}

/**
 * CMS-driven hero carousel with presentation fallback slides when needed.
 * CTA opens Browse for the currently selected journey.
 */
export function HeroSection({ title, subtitle, slides }: Props) {
  const [index, setIndex] = useState(0);
  const scrolling = useRef(false);

  if (slides.length === 0) {
    return null;
  }

  function onScrollEnd(event: NativeSyntheticEvent<NativeScrollEvent>) {
    const next = Math.round(event.nativeEvent.contentOffset.x / SCREEN_WIDTH);
    setIndex(Math.max(0, Math.min(next, slides.length - 1)));
    scrolling.current = false;
  }

  return (
    <View style={styles.section}>
      {(title || subtitle) && (
        <View style={styles.copyHeader}>
          {title ? <Text style={styles.sectionEyebrow}>{title}</Text> : null}
          {subtitle ? <Text style={styles.sectionSubtitle}>{subtitle}</Text> : null}
        </View>
      )}

      <ScrollView
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={onScrollEnd}
        decelerationRate="fast"
        style={styles.carousel}
      >
        {slides.map((slide) => {
          const imageUrl = slideImageUrl(slide);
          const ctaLabel = slide.primary_cta?.label?.trim();
          return (
            <View key={slide.id} style={styles.slide}>
              {slide.localImageSource ? (
                <Image
                  source={slide.localImageSource}
                  style={styles.image}
                  contentFit="cover"
                  accessibilityLabel={slide.headline ?? 'Hero'}
                />
              ) : imageUrl ? (
                <Image
                  source={{ uri: imageUrl }}
                  style={styles.image}
                  contentFit="cover"
                  accessibilityLabel={slide.headline ?? 'Hero'}
                />
              ) : (
                <View
                  style={[
                    styles.image,
                    journeyFallbackStyle(slide.id),
                  ]}
                />
              )}
              <View style={styles.overlay}>
                {slide.eyebrow_text ? (
                  <Text style={styles.eyebrow}>{slide.eyebrow_text}</Text>
                ) : null}
                <Text style={styles.headline} numberOfLines={3}>
                  {slide.headline || 'CHINA ORDER TZ'}
                </Text>
                {slide.subheadline ? (
                  <Text style={styles.subheadline} numberOfLines={3}>
                    {slide.subheadline}
                  </Text>
                ) : null}
                {ctaLabel ? (
                  <Pressable
                    style={styles.cta}
                    onPress={() => router.push('/(app)/(tabs)/browse')}
                    accessibilityRole="button"
                    accessibilityLabel={ctaLabel}
                  >
                    <Text style={styles.ctaText}>{ctaLabel}</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          );
        })}
      </ScrollView>

      {slides.length > 1 ? (
        <View style={styles.dots}>
          {slides.map((slide, i) => (
            <View
              key={slide.id}
              style={[styles.dot, i === index ? styles.dotActive : null]}
            />
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: spacing.xxl,
  },
  copyHeader: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.md,
  },
  sectionEyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: spacing.xs,
  },
  sectionSubtitle: {
    ...typography.body,
  },
  carousel: {
    flexGrow: 0,
  },
  slide: {
    width: SCREEN_WIDTH,
    height: HERO_HEIGHT,
    paddingHorizontal: spacing.lg,
  },
  image: {
    width: '100%',
    height: '100%',
    borderRadius: radius.xl,
    backgroundColor: colors.backgroundMuted,
  },
  imageFallback: {
    backgroundColor: colors.primaryMuted,
  },
  imageFallbackChina: {
    backgroundColor: '#1c1917',
  },
  imageFallbackTz: {
    backgroundColor: '#14532d',
  },
  overlay: {
    ...StyleSheet.absoluteFill,
    marginHorizontal: spacing.lg,
    borderRadius: radius.xl,
    padding: spacing.lg,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(17,17,17,0.28)',
  },
  eyebrow: {
    ...typography.caption,
    color: brandColors.goldLight,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  headline: {
    ...typography.heading,
    color: colors.textInverse,
    fontSize: 24,
    lineHeight: 30,
  },
  subheadline: {
    ...typography.body,
    color: 'rgba(255,255,255,0.92)',
    marginTop: spacing.xs,
  },
  cta: {
    alignSelf: 'flex-start',
    marginTop: spacing.md,
    backgroundColor: colors.primary,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderRadius: radius.full,
  },
  ctaText: {
    ...typography.label,
    color: colors.onPrimary,
    fontWeight: '700',
  },
  dots: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.xs,
    marginTop: spacing.md,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.borderStrong,
  },
  dotActive: {
    width: 16,
    backgroundColor: colors.primary,
  },
});
