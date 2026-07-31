import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(
  request: Request,
  context: { params: Promise<{ purchaseOrder: string }> },
) {
  const { purchaseOrder } = await context.params;
  const body = await request.json().catch(() => ({}));
  return proxyAdminApiRequest(
    `/purchase-orders/${encodeURIComponent(purchaseOrder)}/supplier-response`,
    {
      method: "POST",
      body,
    },
  );
}
