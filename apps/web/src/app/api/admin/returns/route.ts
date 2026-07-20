import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/returns → Laravel GET /api/v1/admin/returns */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "status",
    "order_id",
    "customer_id",
  ]);
  return proxyAdminApiRequest("/returns", { method: "GET", searchParams });
}
