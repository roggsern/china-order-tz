import type {
  VariantBulkActionKey,
  VariantBulkActionPayload,
  VariantBulkActionResponse,
} from "@/lib/admin/variant-bulk";

export class AdminVariantBulkApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminVariantBulkApiError";
  }
}

export async function executeBulkVariantAction(input: {
  productId: string;
  actionKey: VariantBulkActionKey;
  variantIds: string[];
  payload?: VariantBulkActionPayload;
}): Promise<VariantBulkActionResponse> {
  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(input.productId)}/variants/bulk-action`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action_key: input.actionKey,
        variant_ids: input.variantIds,
        payload: input.payload ?? {},
      }),
      cache: "no-store",
    },
  );

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: VariantBulkActionResponse;
    errors?: Record<string, string[]>;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
    throw new AdminVariantBulkApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to execute bulk variant action.",
      response.status,
    );
  }

  return payload.data;
}
