import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ fulfillment: string }> };

/** POST /api/admin/shipments/create/[fulfillment] */
export async function POST(request: Request, context: RouteContext) {
  const { fulfillment } = await context.params;
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyAdminApiRequest(`/shipments/create/${encodeURIComponent(fulfillment)}`, {
    method: "POST",
    body,
  });
}
