import type {
  BulkFulfillmentActionResponse,
  FulfillmentBulkActionKey,
} from "@/lib/admin/fulfillment-bulk";

export class AdminFulfillmentBulkApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminFulfillmentBulkApiError";
  }
}

export async function executeBulkFulfillmentAction(input: {
  actionKey: FulfillmentBulkActionKey;
  fulfillmentIds: string[];
}): Promise<BulkFulfillmentActionResponse> {
  const response = await fetch("/api/admin/fulfillments/bulk-action", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action_key: input.actionKey,
      fulfillment_ids: input.fulfillmentIds,
    }),
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: BulkFulfillmentActionResponse;
    errors?: Record<string, string[]>;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
    throw new AdminFulfillmentBulkApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to execute bulk fulfilment action.",
      response.status,
    );
  }

  return payload.data;
}
