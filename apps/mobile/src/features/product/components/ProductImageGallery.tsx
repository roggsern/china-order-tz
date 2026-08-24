import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Dimensions,
  Linking,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { WebView } from 'react-native-webview';
import { env } from '@/src/core/config/env';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { CatalogProductVideo } from '../models/types';
import {
  PRODUCT_GALLERY_ASPECT_RATIO,
  resolveProductGalleryImageFit,
} from '../utils/productGalleryFit';
import {
  buildProductVideoEmbedHtml,
  resolveProductVideoEmbedUrl,
  resolveProductVideoExternalUrl,
  resolveProductVideoLabel,
  resolveProductVideoThumbnailUrl,
} from '../utils/productVideo';
import type { ProductGalleryMediaSlide } from '../utils/resolvePdpGalleryMedia';
import {
  galleryImageIdentity,
  pdpGalleryImageProps,
} from '../utils/pdpVariantMedia';
import { useHeldPdpGallerySlides } from '../utils/useHeldPdpGallerySlides';

type Props = {
  slides: ProductGalleryMediaSlide[];
};

const SCREEN_WIDTH = Dimensions.get('window').width;
const imageFit = resolveProductGalleryImageFit();

/** Valid HTTPS origin for YouTube Error 153 Referer / baseUrl identity. */
function videoEmbedOrigin(): string {
  return env.webAppBaseUrl.replace(/\/$/, '') || 'https://chinaordertz.com';
}

function VideoSlide({ video }: { video: CatalogProductVideo }) {
  const [playing, setPlaying] = useState(false);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const origin = videoEmbedOrigin();
  const embedUrl = resolveProductVideoEmbedUrl(video.url, { origin });
  const externalUrl = resolveProductVideoExternalUrl(video.url);
  const thumbnailUrl = resolveProductVideoThumbnailUrl({
    url: video.url,
    thumbnailUrl: video.thumbnailUrl,
  });
  const label = resolveProductVideoLabel({
    title: video.title,
    altText: video.altText,
  });

  async function openExternal() {
    if (!externalUrl) return;
    try {
      await Linking.openURL(externalUrl);
    } catch {
      // Secondary only — keep inline surface.
    }
  }

  if (!embedUrl) {
    return (
      <View style={[styles.slide, styles.videoUnavailable]}>
        <Text style={styles.unavailableText}>Video unavailable</Text>
        {externalUrl ? (
          <Pressable
            onPress={() => void openExternal()}
            style={styles.retryButton}
            accessibilityRole="button"
          >
            <Text style={styles.retryText}>Watch on provider</Text>
          </Pressable>
        ) : null}
      </View>
    );
  }

  if (failed) {
    return (
      <View style={[styles.slide, styles.videoUnavailable]}>
        <Text style={styles.unavailableText}>
          Inline playback is unavailable for this video.
        </Text>
        {externalUrl ? (
          <Pressable
            onPress={() => void openExternal()}
            style={styles.retryButton}
            accessibilityRole="button"
          >
            <Text style={styles.retryText}>Watch on YouTube</Text>
          </Pressable>
        ) : null}
        <Pressable
          onPress={() => {
            setFailed(false);
            setPlaying(true);
            setLoading(true);
          }}
          style={styles.retryButton}
          accessibilityRole="button"
        >
          <Text style={styles.retryText}>Retry inline</Text>
        </Pressable>
      </View>
    );
  }

  if (playing) {
    const html = buildProductVideoEmbedHtml({
      embedUrl: `${embedUrl}${embedUrl.includes('?') ? '&' : '?'}autoplay=1`,
      title: label,
    });
    return (
      <View style={styles.slide}>
        <WebView
          source={{
            html,
            baseUrl: `${origin}/`,
          }}
          style={styles.webview}
          allowsInlineMediaPlayback
          mediaPlaybackRequiresUserAction={false}
          onLoadStart={() => setLoading(true)}
          onLoadEnd={() => setLoading(false)}
          onError={() => {
            setFailed(true);
            setPlaying(false);
            setLoading(false);
          }}
          onHttpError={() => {
            setFailed(true);
            setPlaying(false);
            setLoading(false);
          }}
          originWhitelist={['https://*', 'http://*']}
          setSupportMultipleWindows={false}
          javaScriptEnabled
          domStorageEnabled
          allowsFullscreenVideo
          accessibilityLabel={label}
        />
        {loading ? (
          <View style={styles.loadingOverlay}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : null}
        <View style={styles.playerChrome}>
          <Pressable
            style={styles.pauseChip}
            onPress={() => setPlaying(false)}
            accessibilityRole="button"
            accessibilityLabel="Pause video"
          >
            <Ionicons name="pause" size={16} color={colors.onPrimary} />
          </Pressable>
          {externalUrl ? (
            <Pressable
              style={styles.externalChip}
              onPress={() => void openExternal()}
              accessibilityRole="button"
              accessibilityLabel="Watch on YouTube"
            >
              <Text style={styles.externalChipText}>Open externally</Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    );
  }

  return (
    <Pressable
      style={styles.slide}
      onPress={() => {
        setPlaying(true);
        setLoading(true);
      }}
      accessibilityRole="button"
      accessibilityLabel={`Play ${label}`}
    >
      {thumbnailUrl ? (
        <Image
          source={{ uri: thumbnailUrl }}
          style={styles.poster}
          contentFit="cover"
        />
      ) : (
        <View style={[styles.poster, styles.posterFallback]}>
          <Text style={styles.posterFallbackText}>Video</Text>
        </View>
      )}
      <View style={styles.playOverlay}>
        <View style={styles.playButton}>
          <Ionicons name="play" size={28} color={colors.text} />
        </View>
      </View>
    </Pressable>
  );
}

/**
 * Premium 1:1 PDP media gallery — images contain (full product), videos inline.
 * Does not autoplay with sound; swipe remains horizontal paging.
 */
export function ProductImageGallery({ slides }: Props) {
  const displaySlides = useHeldPdpGallerySlides(slides);
  const galleryKey = galleryImageIdentity(displaySlides);
  const scrollRef = useRef<ScrollView>(null);
  const previousGalleryKey = useRef(galleryKey);
  const [scrollState, setScrollState] = useState({ galleryKey, index: 0 });
  const index =
    scrollState.galleryKey === galleryKey
      ? Math.min(scrollState.index, Math.max(0, displaySlides.length - 1))
      : 0;

  useEffect(() => {
    if (previousGalleryKey.current === galleryKey) return;
    previousGalleryKey.current = galleryKey;
    setScrollState({ galleryKey, index: 0 });
    scrollRef.current?.scrollTo({ x: 0, animated: false });
  }, [galleryKey]);

  function onScrollEnd(event: NativeSyntheticEvent<NativeScrollEvent>) {
    const next = Math.round(event.nativeEvent.contentOffset.x / SCREEN_WIDTH);
    setScrollState({
      galleryKey,
      index: Math.max(0, Math.min(next, displaySlides.length - 1)),
    });
  }

  if (displaySlides.length === 0) {
    return (
      <View
        style={[styles.frame, styles.placeholder]}
        accessibilityLabel="No product image"
      >
        <Text style={styles.placeholderEyebrow}>CHINA ORDER TZ</Text>
        <Text style={styles.placeholderText}>No product image available</Text>
      </View>
    );
  }

  return (
    <View style={styles.frame}>
      <ScrollView
        ref={scrollRef}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={onScrollEnd}
        decelerationRate="fast"
      >
        {displaySlides.map((slide, slideIndex) => {
          if (slide.kind === 'video') {
            return (
              <VideoSlide
                // Remount when leaving the slide so playback cannot continue off-screen.
                key={`${slide.key}-${slideIndex === index ? 'active' : 'idle'}`}
                video={slide.video}
              />
            );
          }

          const uri = slide.image.url;
          return (
            <View key={`image-slot-${slideIndex}`} style={styles.slide}>
              <Image
                source={{ uri: uri ?? undefined }}
                style={styles.image}
                contentFit={imageFit}
                transition={0}
                {...(uri ? pdpGalleryImageProps(uri) : {})}
                accessibilityLabel={slide.image.altText ?? 'Product image'}
              />
            </View>
          );
        })}
      </ScrollView>
      {displaySlides.length > 1 ? (
        <View style={styles.dots}>
          {displaySlides.map((slide, i) => (
            <View
              key={`dot-${slide.key}`}
              style={[styles.dot, i === index ? styles.dotActive : null]}
            />
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  frame: {
    width: SCREEN_WIDTH,
    aspectRatio: PRODUCT_GALLERY_ASPECT_RATIO,
    backgroundColor: colors.surfaceCream,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  slide: {
    width: SCREEN_WIDTH,
    aspectRatio: PRODUCT_GALLERY_ASPECT_RATIO,
    backgroundColor: colors.surfaceCream,
  },
  image: {
    width: '100%',
    height: '100%',
    backgroundColor: colors.surfaceCream,
  },
  poster: {
    width: '100%',
    height: '100%',
    backgroundColor: colors.text,
  },
  posterFallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.backgroundMuted,
  },
  posterFallbackText: {
    ...typography.label,
    color: colors.textMuted,
  },
  playOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.28)',
  },
  playButton: {
    width: 64,
    height: 64,
    borderRadius: radius.full,
    backgroundColor: 'rgba(255,255,255,0.95)',
    alignItems: 'center',
    justifyContent: 'center',
    paddingLeft: 3,
  },
  webview: {
    flex: 1,
    backgroundColor: '#000',
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  playerChrome: {
    position: 'absolute',
    top: spacing.md,
    left: spacing.md,
    right: spacing.md,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  pauseChip: {
    width: 36,
    height: 36,
    borderRadius: radius.full,
    backgroundColor: 'rgba(0,0,0,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  externalChip: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radius.md,
    backgroundColor: 'rgba(0,0,0,0.55)',
  },
  externalChipText: {
    ...typography.caption,
    color: colors.onPrimary,
    fontWeight: '600',
  },
  videoUnavailable: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    backgroundColor: colors.backgroundMuted,
    paddingHorizontal: spacing.lg,
  },
  unavailableText: {
    ...typography.body,
    color: colors.textMuted,
    textAlign: 'center',
  },
  retryButton: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: radius.md,
    backgroundColor: colors.primaryMuted,
  },
  retryText: {
    ...typography.label,
    color: colors.primaryPressed,
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
    backgroundColor: 'rgba(0,0,0,0.25)',
  },
  dotActive: {
    width: 16,
    backgroundColor: colors.primary,
  },
});
