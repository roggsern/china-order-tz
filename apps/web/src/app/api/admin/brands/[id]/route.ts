import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/brands/{id} */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Brand id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/brands/${encodeURIComponent(trimmed)}`, {
    method: "GET",
  });
}

/** PUT /api/admin/brands/{id} */
export async function PUT(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Brand id is required." },
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

  return proxyAdminApiRequest(`/brands/${encodeURIComponent(trimmed)}`, {
    method: "PUT",
    body,
  });
}

/** DELETE /api/admin/brands/{id} */
export async function DELETE(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Brand id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/brands/${encodeURIComponent(trimmed)}`, {
    method: "DELETE",
  });
}
