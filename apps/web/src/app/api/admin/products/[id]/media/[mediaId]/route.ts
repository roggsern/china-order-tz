import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string; mediaId: string }>;
};

/** PUT /api/admin/products/{id}/media/{mediaId} */
export async function PUT(request: Request, context: RouteContext) {
  const { id, mediaId } = await context.params;
  const productId = id?.trim();
  const media = mediaId?.trim();

  if (!productId || !media) {
    return Response.json(
      { success: false, message: "Product id and media id are required." },
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
    `/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(media)}`,
    { method: "PUT", body },
  );
}

/** DELETE /api/admin/products/{id}/media/{mediaId} */
export async function DELETE(_request: Request, context: RouteContext) {
  const { id, mediaId } = await context.params;
  const productId = id?.trim();
  const media = mediaId?.trim();

  if (!productId || !media) {
    return Response.json(
      { success: false, message: "Product id and media id are required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(media)}`,
    { method: "DELETE" },
  );
}
