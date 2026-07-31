import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "status",
    "product_id",
    "customer_id",
    "search",
  ]);

  return proxyAdminApiRequest("/reviews", { method: "GET", searchParams });
}
