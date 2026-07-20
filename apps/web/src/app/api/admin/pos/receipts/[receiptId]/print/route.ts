import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ receiptId: string }> };

export async function POST(request: Request, { params }: Params) {
  const { receiptId } = await params;
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyAdminApiRequest(`/pos/receipts/${receiptId}/print`, { method: "POST", body });
}
