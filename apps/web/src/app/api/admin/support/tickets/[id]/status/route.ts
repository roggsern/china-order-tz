import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ id: string }> };

export async function PATCH(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const body = await request.json();
  return proxyAdminApiRequest(`/support/tickets/${encodeURIComponent(id)}/status`, {
    method: "PATCH",
    body,
  });
}
