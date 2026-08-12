import { useQuery } from '@tanstack/react-query';
import { fetchProductReviews } from '../api/productReviewsApi';

export function productReviewsQueryKey(slug: string) {
  return ['catalog', 'product', 'reviews', slug] as const;
}

export function useProductReviews(productSlug: string | null | undefined) {
  const slug = productSlug?.trim() || '';
  return useQuery({
    queryKey: productReviewsQueryKey(slug),
    queryFn: () => fetchProductReviews(slug),
    enabled: Boolean(slug),
  });
}
