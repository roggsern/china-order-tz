import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ id: string }> };

export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  return proxyAdminApiRequest(`/reviews/${encodeURIComponent(id)}/reject`, {
    method: "POST",
    body,
  });
}
