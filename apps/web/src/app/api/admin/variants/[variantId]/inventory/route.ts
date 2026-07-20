import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ variantId: string }>;
};

/** GET /api/admin/variants/{variantId}/inventory */
export async function GET(_request: Request, context: RouteContext) {
  const { variantId } = await context.params;
  const trimmed = variantId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Variant id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/variants/${encodeURIComponent(trimmed)}/inventory`, {
    method: "GET",
  });
}

/** POST /api/admin/variants/{variantId}/inventory */
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

  return proxyAdminApiRequest(`/variants/${encodeURIComponent(trimmed)}/inventory`, {
    method: "POST",
    body,
  });
}
