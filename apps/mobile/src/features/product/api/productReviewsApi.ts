import { apiClient } from '@/src/core/api';

export type ProductReview = {
  id: string;
  rating: number;
  title: string | null;
  comment: string | null;
  author: string | null;
  verified: boolean;
  createdAt: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapProductReview(raw: unknown): ProductReview | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  const rating = typeof data.rating === 'number' ? data.rating : null;
  if (!id || rating == null) return null;
  return {
    id,
    rating,
    title: stringField(data, 'title'),
    comment: stringField(data, 'comment') ?? stringField(data, 'body'),
    author: stringField(data, 'author'),
    verified: data.verified === true,
    createdAt: stringField(data, 'created_at'),
  };
}

/** GET /products/{slug}/reviews — approved reviews only. */
export async function fetchProductReviews(
  productSlug: string,
): Promise<ProductReview[]> {
  const response = await apiClient.get<unknown>(
    `/products/${encodeURIComponent(productSlug)}/reviews`,
    undefined,
    null,
  );
  const rows = Array.isArray(response.data) ? response.data : [];
  return rows
    .map(mapProductReview)
    .filter((row): row is ProductReview => row !== null);
}
