import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string; variantId: string }>;
};

/** PUT /api/admin/products/{id}/variants/{variantId} */
export async function PUT(request: Request, context: RouteContext) {
  const { id, variantId } = await context.params;
  const productId = id?.trim();
  const variant = variantId?.trim();

  if (!productId || !variant) {
    return Response.json(
      { success: false, message: "Product id and variant id are required." },
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
    `/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variant)}`,
    {
      method: "PUT",
      body,
    },
  );
}

/** DELETE /api/admin/products/{id}/variants/{variantId} */
export async function DELETE(_request: Request, context: RouteContext) {
  const { id, variantId } = await context.params;
  const productId = id?.trim();
  const variant = variantId?.trim();

  if (!productId || !variant) {
    return Response.json(
      { success: false, message: "Product id and variant id are required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variant)}`,
    {
      method: "DELETE",
    },
  );
}
