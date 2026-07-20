import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/shipments → Laravel GET /api/v1/admin/shipments */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "status",
    "transport_mode",
    "order_id",
  ]);
  return proxyAdminApiRequest("/shipments", { method: "GET", searchParams });
}
