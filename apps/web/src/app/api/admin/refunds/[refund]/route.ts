import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ refund: string }> };

export async function GET(_request: Request, context: RouteContext) {
  const { refund } = await context.params;
  return proxyAdminApiRequest(`/refunds/${encodeURIComponent(refund)}`, { method: "GET" });
}
