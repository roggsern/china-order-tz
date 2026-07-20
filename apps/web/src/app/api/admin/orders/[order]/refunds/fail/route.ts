import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** POST /api/admin/orders/[order]/refunds/fail → Laravel */
export async function POST(
  request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  const body = await request.json().catch(() => ({}));
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/refunds/fail`, {
    method: "POST",
    body,
  });
}
