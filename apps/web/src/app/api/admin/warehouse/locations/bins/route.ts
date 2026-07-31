import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["page", "per_page", "zone_id"]);
  return proxyAdminApiRequest("/warehouse/locations/bins", { method: "GET", searchParams });
}
