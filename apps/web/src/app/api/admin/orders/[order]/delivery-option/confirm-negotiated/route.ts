import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ order: string }> };

/** POST confirm negotiated delivery for company handling */
export async function POST(_request: Request, context: RouteContext) {
  const { order } = await context.params;
  return proxyAdminApiRequest(
    `/orders/${encodeURIComponent(order)}/delivery-option/confirm-negotiated`,
    { method: "POST" },
  );
}
