import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ priceId: string }>;
};

/** PUT /api/admin/prices/{priceId} */
export async function PUT(request: Request, context: RouteContext) {
  const { priceId } = await context.params;
  const trimmed = priceId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Price id is required." },
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

  return proxyAdminApiRequest(`/prices/${encodeURIComponent(trimmed)}`, {
    method: "PUT",
    body,
  });
}

/** DELETE /api/admin/prices/{priceId} */
export async function DELETE(_request: Request, context: RouteContext) {
  const { priceId } = await context.params;
  const trimmed = priceId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Price id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/prices/${encodeURIComponent(trimmed)}`, {
    method: "DELETE",
  });
}
