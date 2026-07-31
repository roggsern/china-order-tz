import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["page", "per_page", "facility_id"]);
  return proxyAdminApiRequest("/warehouse/locations/zones", { method: "GET", searchParams });
}
