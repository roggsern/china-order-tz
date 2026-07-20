import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** POST /api/admin/orders/[order]/refunds/complete → Laravel */
export async function POST(
  request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  const body = await request.json().catch(() => ({}));
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/refunds/complete`, {
    method: "POST",
    body,
  });
}
