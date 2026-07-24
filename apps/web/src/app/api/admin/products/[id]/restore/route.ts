import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/products/{id}/restore → Laravel POST /api/v1/admin/products/{id}/restore */
export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/restore`, {
    method: "POST",
  });
}
