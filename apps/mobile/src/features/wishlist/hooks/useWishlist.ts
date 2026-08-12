import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '@/src/core/auth';
import {
  addWishlistItem,
  fetchPublicFeatures,
  fetchWishlist,
  removeWishlistItem,
} from '../api/wishlistApi';

export function wishlistQueryKey() {
  return ['wishlist'] as const;
}

export function publicFeaturesQueryKey() {
  return ['features', 'public'] as const;
}

export function usePublicFeatures() {
  return useQuery({
    queryKey: publicFeaturesQueryKey(),
    queryFn: fetchPublicFeatures,
    staleTime: 5 * 60_000,
  });
}

export function useWishlist() {
  const authStatus = useAuthStore((s) => s.status);
  const features = usePublicFeatures();
  const enabled =
    authStatus === 'authenticated' && (features.data?.wishlist ?? true);

  return useQuery({
    queryKey: wishlistQueryKey(),
    queryFn: fetchWishlist,
    enabled,
  });
}

export function useWishlistToggle(productId: string | null) {
  const authStatus = useAuthStore((s) => s.status);
  const queryClient = useQueryClient();
  const features = usePublicFeatures();
  const wishlistQuery = useWishlist();
  const enabledFeature = features.data?.wishlist ?? true;
  const enabled = enabledFeature && authStatus === 'authenticated';

  const inWishlist = Boolean(
    productId &&
      wishlistQuery.data?.some((item) => item.productId === productId),
  );

  const mutation = useMutation({
    mutationFn: async () => {
      if (!productId) return;
      if (inWishlist) {
        await removeWishlistItem(productId);
      } else {
        await addWishlistItem({ productId });
      }
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: wishlistQueryKey() });
    },
  });

  return {
    enabled: enabledFeature,
    inWishlist,
    pending: mutation.isPending,
    toggle: async () => {
      if (!productId || !enabled) return;
      await mutation.mutateAsync();
    },
  };
}
