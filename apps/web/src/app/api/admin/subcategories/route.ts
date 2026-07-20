import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/subcategories → Laravel GET /api/v1/admin/subcategories */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "department_id",
    "category_id",
    "search",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/subcategories", { method: "GET", searchParams });
}

/** POST /api/admin/subcategories → Laravel POST /api/v1/admin/subcategories */
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

  return proxyAdminApiRequest("/subcategories", { method: "POST", body });
}
