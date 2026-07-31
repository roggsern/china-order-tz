import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = { params: Promise<{ id: string }> };

export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const body = await request.json();
  return proxyCustomerApiRequest(request, `/account/support/tickets/${encodeURIComponent(id)}/messages`, {
    method: "POST",
    body,
  });
}
