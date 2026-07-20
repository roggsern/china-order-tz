import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/fulfillments → Laravel GET /api/v1/admin/fulfillments */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "strategy",
    "status",
    "order_id",
  ]);
  return proxyAdminApiRequest("/fulfillments", { method: "GET", searchParams });
}
