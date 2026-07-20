import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ receiptId: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { receiptId } = await params;
  return proxyAdminApiRequest(`/pos/receipts/${receiptId}`, { method: "GET" });
}
