import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/notifications → Laravel GET /api/v1/admin/notifications */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "channel",
    "status",
    "event_type",
    "customer_id",
  ]);
  return proxyAdminApiRequest("/notifications", { method: "GET", searchParams });
}
