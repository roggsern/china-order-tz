import { getCustomerApiToken } from "@/lib/api/customer-auth";
import type { CustomerReview } from "@/lib/types/catalog";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
  code?: string;
  feature?: string;
};

export type ServerProductReview = {
  id: string;
  rating: number;
  title?: string | null;
  comment: string;
  author?: string;
  verified?: boolean;
  created_at?: string;
};

export class CustomerReviewApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly fieldErrors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerReviewApiError";
  }
}

function apiIdToNumericId(id: string): number {
  let hash = 0;
  for (let i = 0; i < id.length; i += 1) {
    hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
  }
  return hash % 2_000_000_000 || 1;
}

function formatReviewDate(value?: string): string {
  if (!value?.trim()) {
    return "";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return parsed.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

export function mapServerProductReview(review: ServerProductReview): CustomerReview {
  return {
    id: apiIdToNumericId(review.id),
    author: review.author?.trim() || "Customer",
    rating: review.rating,
    date: formatReviewDate(review.created_at),
    title: review.title?.trim() || "",
    comment: review.comment,
    verified: Boolean(review.verified),
  };
}

function formatApiErrorMessage(
  payload: ApiSuccessResponse<unknown>,
  fallback: string,
): string {
  if (payload.message?.trim()) {
    return payload.message.trim();
  }

  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) {
      return first.trim();
    }
  }

  return fallback;
}

async function parseReviewResponse<T>(
  response: Response,
  fallbackError: string,
): Promise<T> {
  const payload = (await response.json()) as ApiSuccessResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new CustomerReviewApiError(
      formatApiErrorMessage(payload, fallbackError),
      response.status,
      payload.errors,
    );
  }

  return payload.data as T;
}

export async function fetchProductReviews(slug: string): Promise<CustomerReview[]> {
  const response = await fetch(`/api/products/${encodeURIComponent(slug)}/reviews`, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const data = await parseReviewResponse<ServerProductReview[]>(
    response,
    "Unable to load reviews.",
  );

  return (data ?? []).map(mapServerProductReview);
}

export type SubmitProductReviewInput = {
  rating: number;
  title?: string;
  comment: string;
};

export async function submitProductReview(
  slug: string,
  input: SubmitProductReviewInput,
  token?: string | null,
): Promise<CustomerReview> {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerReviewApiError("Sign in to write a review.", 401);
  }

  const response = await fetch(`/api/products/${encodeURIComponent(slug)}/reviews`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      rating: input.rating,
      ...(input.title?.trim() ? { title: input.title.trim() } : {}),
      comment: input.comment.trim(),
    }),
    cache: "no-store",
  });

  const data = await parseReviewResponse<ServerProductReview>(
    response,
    "Unable to submit your review.",
  );

  return mapServerProductReview(data);
}

export function isReviewFeatureDisabledError(error: unknown): boolean {
  if (!(error instanceof CustomerReviewApiError)) {
    return false;
  }

  return error.statusCode === 403;
}
