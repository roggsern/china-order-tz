import { Image } from 'expo-image';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import {
  buildSafeProductHref,
  TZ_JOURNEY_AMBIGUOUS_MESSAGE,
} from '@/src/features/product';
import {
  isCatalogSalePrice,
  resolvePlpAvailability,
} from '@/src/features/product/utils/resolvePlpAvailability';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { SearchHit } from '../models/types';
import { resolveHitJourney } from '../utils/resolveHitJourney';

type Props = {
  hit: SearchHit;
  /** Kept for API stability; journey is resolved from hit identity only. */
  journey: CommerceJourney;
};

function hitOriginLabel(hit: SearchHit, resolved: CommerceJourney | null): string {
  if (hit.marketplace === 'tz' || hit.commerceChannelCode === 'TZ_LOCAL') {
    return 'Tanzania';
  }
  if (hit.marketplace === 'china' || hit.commerceChannelCode === 'CHINA_IMPORT') {
    return 'China';
  }
  if (resolved === 'TZ_LOCAL') return 'Tanzania';
  if (resolved === 'CHINA_IMPORT') return 'China';
  return 'Marketplace';
}

export function SearchResultCard({ hit }: Props) {
  const resolvedJourney = resolveHitJourney(hit);
  const showSale = isCatalogSalePrice(hit.price, hit.compareAtPrice);
  const availability = resolvePlpAvailability({
    isPurchasable: hit.isPurchasable,
    availabilityStatus: hit.availabilityStatus,
    inStock: hit.inStock,
    commerceChannelCode: hit.commerceChannelCode ?? resolvedJourney,
  });

  function openProduct() {
    if (!resolvedJourney) {
      Alert.alert('Unavailable', TZ_JOURNEY_AMBIGUOUS_MESSAGE);
      return;
    }

    const result = buildSafeProductHref({
      slug: hit.slug,
      journey: resolvedJourney,
      productStoreSlug: hit.storeSlug,
    });
    if (!result.ok) {
      Alert.alert('Store required', result.message);
      return;
    }
    router.push(result.href as never);
  }

  return (
    <Pressable
      style={styles.pressable}
      onPress={openProduct}
      accessibilityRole="button"
      accessibilityLabel={hit.name}
    >
      <Card elevated padded={false} style={styles.card}>
        <View style={styles.imageWrap}>
          {hit.imageUrl ? (
            <Image
              source={{ uri: hit.imageUrl }}
              style={styles.image}
              contentFit="cover"
              transition={200}
            />
          ) : (
            <View style={[styles.image, styles.placeholder]}>
              <Text style={styles.placeholderText}>No image</Text>
            </View>
          )}
          <View style={styles.badgeRow}>
            <Badge
              label={hitOriginLabel(hit, resolvedJourney)}
              tone={resolvedJourney === 'TZ_LOCAL' ? 'success' : 'brand'}
            />
            {showSale ? <Badge label="Sale" tone="warning" /> : null}
            {availability.badgeLabel ? (
              <Badge
                label={availability.badgeLabel}
                tone={availability.kind === 'unavailable' ? 'error' : 'neutral'}
              />
            ) : null}
          </View>
        </View>
        <View style={styles.body}>
          <Text style={styles.name} numberOfLines={2}>
            {hit.name}
          </Text>
          <View style={styles.priceRow}>
            <PriceText value={hit.price} style={styles.price} />
            {showSale ? (
              <PriceText
                value={hit.compareAtPrice}
                accessibilityLabelPrefix="Was"
                style={styles.compare}
              />
            ) : null}
          </View>
          {hit.brandName || hit.storeName ? (
            <Text style={styles.meta} numberOfLines={1}>
              {[hit.brandName, hit.storeName].filter(Boolean).join(' · ')}
            </Text>
          ) : null}
        </View>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressable: {
    width: '48%',
    marginBottom: spacing.md,
  },
  card: {
    borderRadius: radius.xl,
  },
  imageWrap: {
    backgroundColor: colors.backgroundMuted,
  },
  image: {
    width: '100%',
    aspectRatio: 1,
    backgroundColor: colors.backgroundMuted,
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    ...typography.caption,
  },
  badgeRow: {
    position: 'absolute',
    top: spacing.sm,
    left: spacing.sm,
    right: spacing.sm,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
  },
  body: {
    padding: spacing.sm,
  },
  name: {
    ...typography.label,
    color: colors.text,
    marginBottom: spacing.xs,
    minHeight: 34,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: spacing.xs,
  },
  price: {
    fontSize: 14,
  },
  compare: {
    ...typography.caption,
    textDecorationLine: 'line-through',
    color: colors.textSubtle,
    fontWeight: '400',
  },
  meta: {
    marginTop: spacing.xxs,
    ...typography.caption,
  },
});
