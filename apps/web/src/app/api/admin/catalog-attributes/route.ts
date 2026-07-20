import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/catalog-attributes */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "search",
    "type",
    "is_filterable",
    "is_active",
    "trashed",
  ]);
  return proxyAdminApiRequest("/catalog-attributes", {
    method: "GET",
    searchParams,
  });
}

/** POST /api/admin/catalog-attributes */
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

  return proxyAdminApiRequest("/catalog-attributes", { method: "POST", body });
}
