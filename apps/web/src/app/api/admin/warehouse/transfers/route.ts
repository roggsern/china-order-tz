import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["page", "per_page", "status"]);
  return proxyAdminApiRequest("/warehouse/transfers", { method: "GET", searchParams });
}
