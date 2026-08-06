import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** DELETE /api/admin/products/{id}/force → Laravel DELETE /api/v1/admin/products/{id}/force */
export async function DELETE(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  let body: unknown = undefined;
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/force`, {
    method: "DELETE",
    body,
  });
}
