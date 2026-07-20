import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/departments → Laravel GET /api/v1/admin/departments */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "search",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/departments", { method: "GET", searchParams });
}

/** POST /api/admin/departments → Laravel POST /api/v1/admin/departments */
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

  return proxyAdminApiRequest("/departments", { method: "POST", body });
}
