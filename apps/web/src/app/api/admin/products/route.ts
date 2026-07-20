import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/products → Laravel GET /api/v1/admin/products */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "search",
    "category",
    "category_id",
    "subcategory_id",
    "department_id",
    "brand",
    "brand_id",
    "catalog_product_type_id",
    "status",
    "featured",
    "is_featured",
    "trashed",
    "is_demo",
    "sort",
    "direction",
  ]);

  return proxyAdminApiRequest("/products", { method: "GET", searchParams });
}

/** POST /api/admin/products → Laravel POST /api/v1/admin/products */
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

  return proxyAdminApiRequest("/products", { method: "POST", body });
}
