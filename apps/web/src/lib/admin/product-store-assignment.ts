import type { CommerceJourney } from "@/lib/api/admin-catalog";
import { isTzLocalCommerceChannel } from "@/lib/admin/product-shipping-sync";

export function shouldShowProductStoreSelector(input: {
  isNewProduct: boolean;
  commerceJourney: CommerceJourney | "";
  commerceChannelCode: string | null | undefined;
}): boolean {
  if (input.isNewProduct) {
    return input.commerceJourney === "tz";
  }

  return isTzLocalCommerceChannel(input.commerceChannelCode);
}

export function resolveProductStoreIdForReadiness(input: {
  formStoreId: string;
  publishContextStoreId: string | null | undefined;
  commerceChannelCode: string | null | undefined;
  commerceJourney: CommerceJourney | "";
  isNewProduct: boolean;
}): string | null {
  if (
    !shouldShowProductStoreSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return null;
  }

  const trimmed = input.formStoreId.trim();
  if (trimmed) {
    return trimmed;
  }

  return input.publishContextStoreId?.trim() || null;
}

export function validateProductStoreAssignment(input: {
  isNewProduct: boolean;
  commerceJourney: CommerceJourney | "";
  commerceChannelCode: string | null | undefined;
  storeId: string;
  /** When false, skip validation for draft-first wizard early steps. */
  requireAssignment?: boolean;
}): string | null {
  if (input.requireAssignment === false) {
    return null;
  }
  if (
    !shouldShowProductStoreSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return null;
  }

  if (!input.storeId.trim()) {
    return "Select a store for Buy From Tanzania products.";
  }

  return null;
}

export function mergeProductStoreIdIntoPayload<T extends Record<string, unknown>>(
  payload: T,
  input: {
    isNewProduct: boolean;
    commerceJourney: CommerceJourney | "";
    commerceChannelCode: string | null | undefined;
    storeId: string;
  },
): T & { store_id?: string } {
  if (
    !shouldShowProductStoreSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return payload;
  }

  const trimmed = input.storeId.trim();
  if (!trimmed) {
    return payload;
  }

  return {
    ...payload,
    store_id: trimmed,
  };
}
