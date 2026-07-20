import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ sessionId: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { sessionId } = await params;
  return proxyAdminApiRequest(`/pos/sessions/${sessionId}`, { method: "GET" });
}
