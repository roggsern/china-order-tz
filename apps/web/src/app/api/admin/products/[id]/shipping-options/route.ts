import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/products/{id}/shipping-options */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/shipping-options`, {
    method: "GET",
  });
}
