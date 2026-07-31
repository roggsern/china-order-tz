import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ id: string }> };

export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/support/tickets/${encodeURIComponent(id)}`, { method: "GET" });
}
