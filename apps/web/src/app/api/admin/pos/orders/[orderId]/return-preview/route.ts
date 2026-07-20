import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ orderId: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { orderId } = await params;
  return proxyAdminApiRequest(`/pos/orders/${orderId}/return-preview`, { method: "GET" });
}
