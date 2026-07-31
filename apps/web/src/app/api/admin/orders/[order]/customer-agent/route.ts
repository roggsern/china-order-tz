import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(
  _request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/customer-agent`, {
    method: "GET",
  });
}

export async function POST(
  _request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/customer-agent/bootstrap`, {
    method: "POST",
    body: {},
  });
}
