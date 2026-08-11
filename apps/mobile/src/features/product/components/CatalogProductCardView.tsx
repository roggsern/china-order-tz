import { Image } from 'expo-image';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { buildSafeProductHref } from '../utils/buildSafeProductHref';
import type { CatalogProductCard } from '../models/types';

type Props = {
  product: CatalogProductCard;
  journey: CommerceJourney;
  /** Store that already scoped this catalog fetch (Browse only). */
  storeSlug?: string | null;
};

export function CatalogProductCardView({ product, journey, storeSlug }: Props) {
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
    <Pressable style={styles.card} onPress={openProduct}>
      {product.imageUrl ? (
        <Image source={{ uri: product.imageUrl }} style={styles.image} contentFit="cover" />
      ) : (
        <View style={[styles.image, styles.placeholder]}>
          <Text style={styles.placeholderText}>No image</Text>
        </View>
      )}
      <Text style={styles.name} numberOfLines={2}>
        {product.name}
      </Text>
      <Text style={styles.price}>
        {product.price != null ? String(product.price) : '—'}
      </Text>
      {product.availabilityStatus ? (
        <Text style={styles.availability}>{product.availabilityStatus}</Text>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    width: '47%',
    marginBottom: 16,
  },
  image: {
    width: '100%',
    aspectRatio: 1,
    borderRadius: 8,
    backgroundColor: '#eee',
    marginBottom: 8,
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    fontSize: 11,
    color: '#888',
  },
  name: {
    fontSize: 13,
    fontWeight: '600',
    color: '#222',
    marginBottom: 4,
  },
  price: {
    fontSize: 13,
    color: '#0a7ea4',
    fontWeight: '600',
  },
  availability: {
    marginTop: 2,
    fontSize: 11,
    color: '#666',
    textTransform: 'capitalize',
  },
});
