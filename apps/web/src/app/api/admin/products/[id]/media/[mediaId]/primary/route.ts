import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string; mediaId: string }>;
};

/** POST /api/admin/products/{id}/media/{mediaId}/primary */
export async function POST(_request: Request, context: RouteContext) {
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
    `/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(media)}/primary`,
    { method: "POST" },
  );
}
