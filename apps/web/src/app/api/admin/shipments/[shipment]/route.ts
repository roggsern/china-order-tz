import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ shipment: string }> };

/** GET /api/admin/shipments/[shipment] */
export async function GET(_request: Request, context: RouteContext) {
  const { shipment } = await context.params;
  return proxyAdminApiRequest(`/shipments/${encodeURIComponent(shipment)}`, {
    method: "GET",
  });
}
