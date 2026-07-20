import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(
  request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;
  const body = await request.json().catch(() => ({}));
  return proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}/china-workflow/agent-handoff`, {
    method: "POST",
    body,
  });
}
