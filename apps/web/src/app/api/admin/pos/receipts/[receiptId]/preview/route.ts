import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ receiptId: string }> };

export async function GET(request: Request, { params }: Params) {
  const { receiptId } = await params;
  const layout = new URL(request.url).searchParams.get("layout") ?? "thermal_80";
  return proxyAdminApiRequest(`/pos/receipts/${receiptId}/preview?layout=${encodeURIComponent(layout)}`, {
    method: "GET",
    raw: true,
    accept: "text/html",
  });
}
