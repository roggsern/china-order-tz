import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ variantId: string }>;
};

/** PATCH /api/admin/variants/{variantId}/commercial-stock */
export async function PATCH(request: Request, context: RouteContext) {
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

  return proxyAdminApiRequest(
    `/variants/${encodeURIComponent(trimmed)}/commercial-stock`,
    {
      method: "PATCH",
      body,
    },
  );
}
