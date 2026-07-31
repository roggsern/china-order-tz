import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(
  _request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/china-workflow/bootstrap`, {
    method: "POST",
    body: {},
  });
}
