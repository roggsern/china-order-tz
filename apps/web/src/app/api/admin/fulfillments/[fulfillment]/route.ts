import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ fulfillment: string }>;
};

/** GET /api/admin/fulfillments/[fulfillment] → Laravel GET /api/v1/admin/fulfillments/{fulfillment} */
export async function GET(_request: Request, context: RouteContext) {
  const { fulfillment } = await context.params;
  return proxyAdminApiRequest(`/fulfillments/${encodeURIComponent(fulfillment)}`, {
    method: "GET",
  });
}
