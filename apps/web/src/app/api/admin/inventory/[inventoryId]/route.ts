import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ inventoryId: string }>;
};

/** PUT /api/admin/inventory/{inventoryId} */
export async function PUT(request: Request, context: RouteContext) {
  const { inventoryId } = await context.params;
  const trimmed = inventoryId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Inventory id is required." },
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

  return proxyAdminApiRequest(`/inventory/${encodeURIComponent(trimmed)}`, {
    method: "PUT",
    body,
  });
}

/** DELETE /api/admin/inventory/{inventoryId} */
export async function DELETE(_request: Request, context: RouteContext) {
  const { inventoryId } = await context.params;
  const trimmed = inventoryId?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Inventory id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/inventory/${encodeURIComponent(trimmed)}`, {
    method: "DELETE",
  });
}
