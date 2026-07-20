import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/catalog-product-types/{id}/restore */
export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product type id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/catalog-product-types/${encodeURIComponent(trimmed)}/restore`,
    { method: "POST" },
  );
}
