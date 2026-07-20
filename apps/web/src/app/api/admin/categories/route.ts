import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/categories → Laravel GET /api/v1/admin/categories */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "department_id",
    "origin",
    "parent_id",
    "roots_only",
    "search",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/categories", { method: "GET", searchParams });
}

/** POST /api/admin/categories → Laravel POST /api/v1/admin/categories */
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

  return proxyAdminApiRequest("/categories", { method: "POST", body });
}
