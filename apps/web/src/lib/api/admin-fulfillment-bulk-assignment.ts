export type BulkFulfillmentAssignmentResult = {
  requested: number;
  changed: number;
  unchanged: number;
  assigned_to: string | null;
  assignee: {
    id: string;
    name: string;
  } | null;
};

export class AdminFulfillmentBulkAssignmentApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminFulfillmentBulkAssignmentApiError";
  }
}

export async function updateAdminFulfillmentBulkAssignment(
  fulfillmentIds: string[],
  assignedTo: string | null,
): Promise<BulkFulfillmentAssignmentResult> {
  const response = await fetch("/api/admin/fulfillments/bulk-assignment", {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      fulfillment_ids: fulfillmentIds,
      assigned_to: assignedTo,
    }),
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: BulkFulfillmentAssignmentResult;
    errors?: Record<string, string[]>;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
    throw new AdminFulfillmentBulkAssignmentApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to update fulfillment assignments.",
      response.status,
    );
  }

  return payload.data;
}
