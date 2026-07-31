import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ refund: string }> };

export async function POST(request: Request, context: RouteContext) {
  const { refund } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  return proxyAdminApiRequest(`/refunds/${encodeURIComponent(refund)}/process`, {
    method: "POST",
    body,
  });
}
