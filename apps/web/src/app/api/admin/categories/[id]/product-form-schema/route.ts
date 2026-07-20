import {
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/categories/[id]/product-form-schema */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyAdminApiRequest(
    `/categories/${encodeURIComponent(id)}/product-form-schema`,
    { method: "GET" },
  );
}
