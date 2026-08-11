import { Image } from 'expo-image';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { journeyLabelFromChannel } from '@/src/features/cart/utils/journeyLabel';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { buildSafeProductHref } from '../utils/buildSafeProductHref';
import {
  isCatalogSalePrice,
  resolvePlpAvailability,
} from '../utils/resolvePlpAvailability';
import type { CatalogProductCard } from '../models/types';

type Props = {
  product: CatalogProductCard;
  journey: CommerceJourney;
  /** Store that already scoped this catalog fetch (Browse only). */
  storeSlug?: string | null;
};

function originLabel(
  product: CatalogProductCard,
  journey: CommerceJourney,
): string {
  const fromServer = product.commerceSourceLabel?.trim();
  if (fromServer) return fromServer;
  const code = product.commerceChannelCode ?? journey;
  if (code === 'TZ_LOCAL') return 'Tanzania';
  if (code === 'CHINA_IMPORT') return 'China';
  return journeyLabelFromChannel(code);
}

function originTone(
  product: CatalogProductCard,
  journey: CommerceJourney,
): 'brand' | 'success' {
  const code = product.commerceChannelCode ?? journey;
  return code === 'TZ_LOCAL' ? 'success' : 'brand';
}

export function CatalogProductCardView({ product, journey, storeSlug }: Props) {
  const showSale = isCatalogSalePrice(product.price, product.compareAtPrice);
  const availability = resolvePlpAvailability({
    isPurchasable: product.isPurchasable,
    availabilityStatus: product.availabilityStatus,
    inStock: product.inStock,
    commerceChannelCode: product.commerceChannelCode ?? journey,
  });

  function openProduct() {
    const result = buildSafeProductHref({
      slug: product.slug,
      journey,
      productStoreSlug: product.storeSlug ?? null,
      browseScopedStoreSlug: storeSlug,
    });
    if (!result.ok) return;
    router.push(result.href as never);
  }

  return (
    <Pressable
      style={styles.pressable}
      onPress={openProduct}
      accessibilityRole="button"
      accessibilityLabel={product.name}
    >
      <Card elevated padded={false} style={styles.card}>
        <View style={styles.imageWrap}>
          {product.imageUrl ? (
            <Image
              source={{ uri: product.imageUrl }}
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
              label={originLabel(product, journey)}
              tone={originTone(product, journey)}
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
            {product.name}
          </Text>
          {product.brand?.name ? (
            <Text style={styles.brand} numberOfLines={1}>
              {product.brand.name}
            </Text>
          ) : null}
          <View style={styles.priceRow}>
            <PriceText value={product.price} style={styles.price} />
            {showSale ? (
              <PriceText
                value={product.compareAtPrice}
                accessibilityLabelPrefix="Was"
                style={styles.compare}
              />
            ) : null}
          </View>
        </View>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressable: {
    width: '100%',
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
    marginBottom: spacing.xxs,
    minHeight: 34,
  },
  brand: {
    ...typography.caption,
    marginBottom: spacing.xs,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'center',
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
});
