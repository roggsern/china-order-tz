import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import {
  buildSafeProductHref,
  TZ_JOURNEY_AMBIGUOUS_MESSAGE,
} from '@/src/features/product';
import { Badge } from '@/src/shared/ui/Badge';
import { PriceText } from '@/src/shared/ui/PriceText';
import { listImageProps } from '@/src/shared/media/listImageProps';
import { colors, radius, shadows, spacing, typography } from '@/src/shared/theme';
import type { HomepageProductCard } from '../models/types';
import { resolveHomepageProductJourney } from '../utils/resolveHomepageProductJourney';

type Props = {
  product: HomepageProductCard;
  badgeLabel?: string;
};

export function ProductCard({ product, badgeLabel }: Props) {
  const showSale =
    product.compareAtPrice != null &&
    product.price != null &&
    Number(product.compareAtPrice) > Number(product.price);

  function openProduct() {
    const journey = resolveHomepageProductJourney(product);
    if (!journey) {
      Alert.alert('Unavailable', TZ_JOURNEY_AMBIGUOUS_MESSAGE);
      return;
    }

    const result = buildSafeProductHref({
      slug: product.slug,
      journey,
      productStoreSlug: product.storeSlug,
    });
    if (!result.ok) {
      Alert.alert('Store required', result.message);
      return;
    }
    router.push(result.href as never);
  }

  return (
    <Pressable style={styles.card} onPress={openProduct}>
      <View style={styles.imageWrap}>
        {product.imageUrl ? (
          <Image
            source={{ uri: product.imageUrl }}
            style={styles.image}
            contentFit="cover"
            {...listImageProps(product.imageUrl)}
          />
        ) : (
          <View style={[styles.image, styles.imagePlaceholder]}>
            <Text style={styles.placeholderText}>No image</Text>
          </View>
        )}
        {badgeLabel ? (
          <Badge label={badgeLabel} tone="brand" style={styles.badge} />
        ) : showSale ? (
          <Badge label="Sale" tone="warning" style={styles.badge} />
        ) : null}
      </View>
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
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    width: 152,
    marginRight: spacing.md,
  },
  imageWrap: {
    marginBottom: spacing.sm,
    ...shadows.sm,
  },
  image: {
    width: 152,
    height: 152,
    borderRadius: radius.xl,
    backgroundColor: colors.backgroundMuted,
  },
  imagePlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    ...typography.caption,
  },
  badge: {
    position: 'absolute',
    top: spacing.sm,
    left: spacing.sm,
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
  },
});
