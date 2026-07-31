import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = { params: Promise<{ id: string }> };

export async function GET(request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyCustomerApiRequest(request, `/account/support/tickets/${encodeURIComponent(id)}`, {
    method: "GET",
  });
}
