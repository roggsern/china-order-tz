export type PublicFeatureFlags = {
  wishlist: boolean;
  reviews: boolean;
  new_checkout: boolean;
};

export const DEFAULT_PUBLIC_FEATURE_FLAGS: PublicFeatureFlags = {
  wishlist: false,
  reviews: false,
  new_checkout: false,
};

export type PublicFeaturesResponse = {
  success?: boolean;
  data?: Partial<PublicFeatureFlags>;
  wishlist?: boolean;
  reviews?: boolean;
  new_checkout?: boolean;
};

export function mapPublicFeatureFlags(
  payload: PublicFeaturesResponse | Partial<PublicFeatureFlags> | undefined,
): PublicFeatureFlags {
  const source =
    payload && "data" in payload && payload.data && typeof payload.data === "object"
      ? payload.data
      : payload;

  return {
    wishlist: Boolean(source?.wishlist),
    reviews: Boolean(source?.reviews),
    new_checkout: Boolean(source?.new_checkout),
  };
}

export function isFeatureDisabledResponse(
  status: number,
  body: { code?: string; feature?: string } | null | undefined,
): boolean {
  return status === 403 && body?.code === "feature_disabled";
}
