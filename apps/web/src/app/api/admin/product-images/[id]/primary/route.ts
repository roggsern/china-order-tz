import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** PATCH /api/admin/product-images/{id}/primary */
export async function PATCH(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Image id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/product-images/${encodeURIComponent(trimmed)}/primary`, {
    method: "PATCH",
  });
}
