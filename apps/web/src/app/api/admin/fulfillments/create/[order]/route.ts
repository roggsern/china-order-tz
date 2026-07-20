import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ order: string }>;
};

/** POST /api/admin/fulfillments/create/[order] → Laravel POST /api/v1/admin/fulfillments/create/{order} */
export async function POST(_request: Request, context: RouteContext) {
  const { order } = await context.params;
  return proxyAdminApiRequest(`/fulfillments/create/${encodeURIComponent(order)}`, {
    method: "POST",
  });
}
