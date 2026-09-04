import { resolveVisibleSelectedIds } from "@/lib/admin/table-selection";

export type BulkFulfillmentAssignmentPayload = {
  fulfillment_ids: string[];
  assigned_to: string | null;
};

export function buildBulkAssignmentPayload(
  selectedIds: Set<string>,
  visibleIds: readonly string[],
  assignedTo: string | null,
): BulkFulfillmentAssignmentPayload {
  return {
    fulfillment_ids: resolveVisibleSelectedIds(selectedIds, visibleIds),
    assigned_to: assignedTo,
  };
}

export function buildBulkAssignmentSuccessMessage(input: {
  requested: number;
  assignedTo: string | null;
  assigneeName?: string | null;
  hadExistingAssignee?: boolean;
}): string {
  const count = input.requested;
  const noun = count === 1 ? "fulfillment" : "fulfillments";

  if (input.assignedTo === null) {
    return `Unassigned ${count} ${noun}`;
  }

  const name = input.assigneeName?.trim() || "the selected operator";
  const verb = input.hadExistingAssignee ? "Reassigned" : "Assigned";

  return `${verb} ${count} ${noun} to ${name}`;
}
