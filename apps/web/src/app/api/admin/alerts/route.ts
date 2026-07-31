/** GET /api/admin/alerts → Laravel GET /api/v1/admin/alerts */
import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["from", "to"]);
  return proxyAdminApiRequest("/alerts", { method: "GET", searchParams });
}
