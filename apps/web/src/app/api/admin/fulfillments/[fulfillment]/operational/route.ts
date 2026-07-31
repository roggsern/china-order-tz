import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ fulfillment: string }>;
};

/** GET /api/admin/fulfillments/[fulfillment]/operational → Laravel operational read model */
export async function GET(_request: Request, context: RouteContext) {
  const { fulfillment } = await context.params;
  return proxyAdminApiRequest(`/fulfillments/${encodeURIComponent(fulfillment)}/operational`, {
    method: "GET",
  });
}
