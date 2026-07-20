import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ variantId: string }>;
};

/** GET /api/admin/variants/{variantId}/prices */
export async function GET(_request: Request, context: RouteContext) {
  const { variantId } = await context.params;
  const trimmed = variantId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Variant id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/variants/${encodeURIComponent(trimmed)}/prices`, {
    method: "GET",
  });
}

/** POST /api/admin/variants/{variantId}/prices */
export async function POST(request: Request, context: RouteContext) {
  const { variantId } = await context.params;
  const trimmed = variantId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Variant id is required." },
      { status: 422 },
    );
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/variants/${encodeURIComponent(trimmed)}/prices`, {
    method: "POST",
    body,
  });
}
