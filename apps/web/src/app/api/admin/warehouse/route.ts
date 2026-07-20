import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/warehouse → Laravel GET /api/v1/admin/warehouse */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "status",
    "order_id",
  ]);
  return proxyAdminApiRequest("/warehouse", { method: "GET", searchParams });
}
