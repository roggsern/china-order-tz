import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** PATCH /api/admin/orders/[order]/pay → Laravel admin pay confirmation */
export async function PATCH(
  request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  const body = await request.json().catch(() => ({}));
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/pay`, {
    method: "PATCH",
    body,
  });
}
