import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/brands → Laravel GET /api/v1/admin/brands */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "category_id",
    "search",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/brands", { method: "GET", searchParams });
}

/** POST /api/admin/brands → Laravel POST /api/v1/admin/brands */
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

  return proxyAdminApiRequest("/brands", { method: "POST", body });
}
