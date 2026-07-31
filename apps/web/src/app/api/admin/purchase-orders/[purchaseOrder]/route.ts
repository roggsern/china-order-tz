import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(
  _request: Request,
  context: { params: Promise<{ purchaseOrder: string }> },
) {
  const { purchaseOrder } = await context.params;
  return proxyAdminApiRequest(`/purchase-orders/${purchaseOrder}`, { method: "GET" });
}
