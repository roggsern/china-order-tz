import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import {
  buildSafeProductHref,
  TZ_JOURNEY_AMBIGUOUS_MESSAGE,
} from '@/src/features/product';
import type { HomepageProductCard } from '../models/types';
import { resolveHomepageProductJourney } from '../utils/resolveHomepageProductJourney';

type Props = {
  product: HomepageProductCard;
};

export function ProductCard({ product }: Props) {
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
      {product.imageUrl ? (
        <Image source={{ uri: product.imageUrl }} style={styles.image} contentFit="cover" />
      ) : (
        <View style={[styles.image, styles.imagePlaceholder]}>
          <Text style={styles.placeholderText}>No image</Text>
        </View>
      )}
      <Text style={styles.name} numberOfLines={2}>
        {product.name}
      </Text>
      <Text style={styles.price}>
        {product.price != null ? String(product.price) : '—'}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    width: 140,
    marginRight: 12,
  },
  image: {
    width: 140,
    height: 140,
    borderRadius: 8,
    backgroundColor: '#eee',
    marginBottom: 8,
  },
  imagePlaceholder: {
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
    marginBottom: 4,
    color: '#222',
  },
  price: {
    fontSize: 13,
    color: '#0a7ea4',
  },
});
