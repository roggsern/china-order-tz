import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/catalog-product-types → Laravel GET /api/v1/admin/catalog-product-types */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "department_id",
    "category_id",
    "subcategory_id",
    "search",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/catalog-product-types", {
    method: "GET",
    searchParams,
  });
}

/** POST /api/admin/catalog-product-types */
export async function POST(request: Request) {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest("/catalog-product-types", { method: "POST", body });
}
