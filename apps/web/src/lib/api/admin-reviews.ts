import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminReviewsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminReviewsApiError";
  }
}

export type AdminReviewRecord = {
  id: string;
  rating: number;
  title: string | null;
  comment: string | null;
  status: string;
  status_label: string;
  is_approved: boolean;
  is_verified_purchase: boolean;
  moderation_note: string | null;
  product_id: string;
  user_id: string;
  order_id: string | null;
  created_at: string | null;
  updated_at: string | null;
  moderated_at: string | null;
  customer?: { id: string; name: string; email: string } | null;
  product?: { id: string; name: string; slug: string } | null;
  order?: { id: string; order_number: string } | null;
  moderated_by?: { id: string; name: string } | null;
};

export function canViewAdminReviews(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "reviews.view");
}

export function canManageAdminReviews(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "reviews.manage");
}

export function reviewStatusLabel(status: string): string {
  switch (status) {
    case "pending":
      return "Pending";
    case "approved":
      return "Approved";
    case "rejected":
      return "Rejected";
    default:
      return status;
  }
}

export function reviewStatusBadgeClass(status: string): string {
  switch (status) {
    case "approved":
      return "bg-emerald-50 text-emerald-700 ring-emerald-600/20";
    case "rejected":
      return "bg-red-50 text-red-700 ring-red-600/20";
    case "pending":
    default:
      return "bg-amber-50 text-amber-700 ring-amber-600/20";
  }
}

export async function fetchAdminReviews(filters?: {
  status?: string;
  search?: string;
  product_id?: string;
  customer_id?: string;
}): Promise<AdminReviewRecord[]> {
  const params = new URLSearchParams();
  if (filters?.status) params.set("status", filters.status);
  if (filters?.search) params.set("search", filters.search);
  if (filters?.product_id) params.set("product_id", filters.product_id);
  if (filters?.customer_id) params.set("customer_id", filters.customer_id);
  const qs = params.toString() ? `?${params.toString()}` : "";

  const response = await fetch(`/api/admin/reviews${qs}`, {
    credentials: "include",
    cache: "no-store",
  });

  let payload: { success?: boolean; message?: string; data?: AdminReviewRecord[] } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !Array.isArray(payload.data)) {
    throw new AdminReviewsApiError(
      payload.message?.trim() || "Unable to load reviews.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchAdminReview(id: string): Promise<AdminReviewRecord> {
  const response = await fetch(`/api/admin/reviews/${encodeURIComponent(id)}`, {
    credentials: "include",
    cache: "no-store",
  });

  let payload: { success?: boolean; message?: string; data?: AdminReviewRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminReviewsApiError(
      payload.message?.trim() || "Unable to load review.",
      response.status,
    );
  }

  return payload.data;
}

export async function approveAdminReview(
  id: string,
  moderationNote?: string,
): Promise<AdminReviewRecord> {
  const response = await fetch(`/api/admin/reviews/${encodeURIComponent(id)}/approve`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ moderation_note: moderationNote ?? null }),
  });

  let payload: { success?: boolean; message?: string; data?: AdminReviewRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminReviewsApiError(
      payload.message?.trim() || "Unable to approve review.",
      response.status,
    );
  }

  return payload.data;
}

export async function rejectAdminReview(
  id: string,
  moderationNote?: string,
): Promise<AdminReviewRecord> {
  const response = await fetch(`/api/admin/reviews/${encodeURIComponent(id)}/reject`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ moderation_note: moderationNote ?? null }),
  });

  let payload: { success?: boolean; message?: string; data?: AdminReviewRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminReviewsApiError(
      payload.message?.trim() || "Unable to reject review.",
      response.status,
    );
  }

  return payload.data;
}
