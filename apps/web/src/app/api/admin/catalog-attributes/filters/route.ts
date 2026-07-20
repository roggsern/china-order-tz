import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/catalog-attributes/filters */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "catalog_product_type_id",
  ]);
  return proxyAdminApiRequest("/catalog-attributes/filters", {
    method: "GET",
    searchParams,
  });
}
