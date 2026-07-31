import type { CommerceJourney } from "@/lib/api/admin-catalog";
import { isChinaImportCommerceChannel } from "@/lib/admin/product-shipping-sync";

export function shouldShowProductSupplierSelector(input: {
  isNewProduct: boolean;
  commerceJourney: CommerceJourney | "";
  commerceChannelCode: string | null | undefined;
}): boolean {
  if (input.isNewProduct) {
    return input.commerceJourney === "china";
  }

  return isChinaImportCommerceChannel(input.commerceChannelCode);
}

export function validateProductSupplierAssignment(input: {
  isNewProduct: boolean;
  commerceJourney: CommerceJourney | "";
  commerceChannelCode: string | null | undefined;
  supplierId: string;
  /** When false, skip validation for draft-first wizard early steps. */
  requireAssignment?: boolean;
}): string | null {
  if (input.requireAssignment === false) {
    return null;
  }
  if (
    !shouldShowProductSupplierSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return null;
  }

  if (!input.supplierId.trim()) {
    return "Select a supplier for Order From China products.";
  }

  return null;
}

export function mergeProductSupplierIdIntoPayload<T extends Record<string, unknown>>(
  payload: T,
  input: {
    isNewProduct: boolean;
    commerceJourney: CommerceJourney | "";
    commerceChannelCode: string | null | undefined;
    supplierId: string;
  },
): T & { supplier_id?: string | null } {
  if (
    !shouldShowProductSupplierSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return payload;
  }

  const trimmed = input.supplierId.trim();

  return {
    ...payload,
    supplier_id: trimmed || null,
  };
}

export function resolveProductSupplierIdForReadiness(input: {
  formSupplierId: string;
  publishContextSupplierId: string | null | undefined;
  commerceChannelCode: string | null | undefined;
  commerceJourney: CommerceJourney | "";
  isNewProduct: boolean;
}): string | null {
  if (
    !shouldShowProductSupplierSelector({
      isNewProduct: input.isNewProduct,
      commerceJourney: input.commerceJourney,
      commerceChannelCode: input.commerceChannelCode,
    })
  ) {
    return null;
  }

  const trimmed = input.formSupplierId.trim();
  if (trimmed) {
    return trimmed;
  }

  return input.publishContextSupplierId?.trim() || null;
}
