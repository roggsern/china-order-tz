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
  const showSale =
    product.compareAtPrice != null &&
    product.price != null &&
    Number(product.compareAtPrice) > Number(product.price);

  const availability = product.availabilityStatus?.trim();
  const outOfStock =
    availability === 'out_of_stock' ||
    availability === 'unavailable' ||
    product.inStock === false;

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
            {outOfStock ? <Badge label="Out of stock" tone="neutral" /> : null}
          </View>
        </View>
        <View style={styles.body}>
          <Text style={styles.name} numberOfLines={2}>
            {product.name}
          </Text>
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
          {availability && !outOfStock ? (
            <Text style={styles.availability} numberOfLines={1}>
              {availability.replace(/_/g, ' ')}
            </Text>
          ) : null}
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
    marginBottom: spacing.xs,
    minHeight: 34,
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
  availability: {
    marginTop: spacing.xxs,
    ...typography.caption,
    textTransform: 'capitalize',
  },
});
