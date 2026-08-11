import { Image } from 'expo-image';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import {
  buildSafeProductHref,
  TZ_JOURNEY_AMBIGUOUS_MESSAGE,
} from '@/src/features/product';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import type { SearchHit } from '../models/types';
import { resolveHitJourney } from '../utils/resolveHitJourney';

type Props = {
  hit: SearchHit;
  /** Kept for API stability; journey is resolved from hit identity only. */
  journey: CommerceJourney;
};

export function SearchResultCard({ hit }: Props) {
  function openProduct() {
    const resolvedJourney = resolveHitJourney(hit);
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
    <Pressable style={styles.card} onPress={openProduct}>
      {hit.imageUrl ? (
        <Image source={{ uri: hit.imageUrl }} style={styles.image} contentFit="cover" />
      ) : (
        <View style={[styles.image, styles.placeholder]}>
          <Text style={styles.placeholderText}>No image</Text>
        </View>
      )}
      <Text style={styles.name} numberOfLines={2}>
        {hit.name}
      </Text>
      <Text style={styles.price}>{hit.price != null ? String(hit.price) : '—'}</Text>
      {hit.brandName || hit.storeName ? (
        <Text style={styles.meta} numberOfLines={1}>
          {[hit.brandName, hit.storeName].filter(Boolean).join(' · ')}
        </Text>
      ) : null}
      {hit.availabilityStatus ? (
        <Text style={styles.availability}>{hit.availabilityStatus}</Text>
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
  meta: {
    marginTop: 2,
    fontSize: 11,
    color: '#666',
  },
  availability: {
    marginTop: 2,
    fontSize: 11,
    color: '#666',
    textTransform: 'capitalize',
  },
});
