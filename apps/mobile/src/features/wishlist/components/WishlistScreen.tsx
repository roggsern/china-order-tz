import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import {
  buildSafeProductHref,
  TZ_JOURNEY_AMBIGUOUS_MESSAGE,
} from '@/src/features/product';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';
import { removeWishlistItem, type WishlistItem } from '../api/wishlistApi';
import { usePublicFeatures, useWishlist, wishlistQueryKey } from '../hooks/useWishlist';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

export function WishlistScreen() {
  const journey = useJourneyStore((s) => s.journey);
  const features = usePublicFeatures();
  const wishlistQuery = useWishlist();
  const queryClient = useQueryClient();
  const [removingId, setRemovingId] = useState<string | null>(null);

  if (features.data && features.data.wishlist === false) {
    return (
      <ScreenContainer>
        <EmptyState
          title="Wishlist unavailable"
          message="Wishlist is currently disabled for this storefront."
        />
      </ScreenContainer>
    );
  }

  if (wishlistQuery.isLoading) {
    return (
      <ScreenContainer>
        <Text style={styles.loading}>Loading wishlist…</Text>
      </ScreenContainer>
    );
  }

  if (wishlistQuery.isError) {
    return (
      <ScreenContainer>
        <EmptyState
          title="Wishlist unavailable"
          message="Could not load your saved products."
          actionLabel="Retry"
          onActionPress={() => void wishlistQuery.refetch()}
        />
      </ScreenContainer>
    );
  }

  const items = wishlistQuery.data ?? [];

  async function removeItem(item: WishlistItem) {
    setRemovingId(item.productId);
    try {
      await removeWishlistItem(item.productId);
      await queryClient.invalidateQueries({ queryKey: wishlistQueryKey() });
    } catch {
      Alert.alert('Unable to remove', 'Please try again.');
    } finally {
      setRemovingId(null);
    }
  }

  function openProduct(item: WishlistItem) {
    const slug = item.productSlug;
    if (!slug) {
      Alert.alert('Unavailable', 'This saved product has no storefront link.');
      return;
    }
    const result = buildSafeProductHref({
      slug,
      journey,
      productStoreSlug: null,
    });
    if (!result.ok) {
      Alert.alert('Store required', result.message || TZ_JOURNEY_AMBIGUOUS_MESSAGE);
      return;
    }
    router.push(result.href as never);
  }

  return (
    <ScreenContainer padded={false}>
      <FlatList
        contentContainerStyle={styles.content}
        data={items}
        keyExtractor={(item) => item.id}
        ListHeaderComponent={
          <View style={styles.header}>
            <Text style={styles.eyebrow}>Account</Text>
            <Text style={styles.heading}>Wishlist</Text>
            <Text style={styles.subheading}>
              Products you saved on CHINA ORDER TZ.
            </Text>
          </View>
        }
        ListEmptyComponent={
          <EmptyState
            title="No saved products"
            message="Tap Add to wishlist on a product page to save it here."
          />
        }
        renderItem={({ item }) => (
          <View style={styles.row}>
            <Pressable style={styles.rowMain} onPress={() => openProduct(item)}>
              <Text style={styles.name} numberOfLines={2}>
                {item.productName ?? 'Product'}
              </Text>
              {item.productSlug ? (
                <Text style={styles.slug} numberOfLines={1}>
                  {item.productSlug}
                </Text>
              ) : null}
            </Pressable>
            <SecondaryButton
              label={removingId === item.productId ? '…' : 'Remove'}
              onPress={() => void removeItem(item)}
              disabled={removingId === item.productId}
              style={styles.remove}
            />
          </View>
        )}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  header: { marginBottom: spacing.lg },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  subheading: {
    ...typography.caption,
    marginTop: spacing.xs,
  },
  loading: {
    ...typography.body,
    padding: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  rowMain: { flex: 1 },
  name: { ...typography.bodyStrong, color: colors.text },
  slug: { ...typography.caption, marginTop: spacing.xxs },
  remove: { minWidth: 96 },
});
