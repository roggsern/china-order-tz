import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/orders/[order]/china-workflow */
export async function GET(
  _request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/china-workflow`, {
    method: "GET",
  });
}
