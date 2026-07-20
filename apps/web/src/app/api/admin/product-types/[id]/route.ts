import {
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/product-types/[id] */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/product-types/${encodeURIComponent(id)}`, {
    method: "GET",
  });
}
