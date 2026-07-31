import type {
  ProductBulkActionKey,
  ProductBulkActionPayload,
  ProductBulkActionResponse,
} from "@/lib/admin/product-bulk";

export class AdminProductBulkApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminProductBulkApiError";
  }
}

export async function executeBulkProductAction(input: {
  actionKey: ProductBulkActionKey;
  productIds: string[];
  payload?: ProductBulkActionPayload;
}): Promise<ProductBulkActionResponse> {
  const response = await fetch("/api/admin/products/bulk-action", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action_key: input.actionKey,
      product_ids: input.productIds,
      payload: input.payload ?? {},
    }),
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: ProductBulkActionResponse;
    errors?: Record<string, string[]>;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
    throw new AdminProductBulkApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to execute bulk product action.",
      response.status,
    );
  }

  return payload.data;
}
