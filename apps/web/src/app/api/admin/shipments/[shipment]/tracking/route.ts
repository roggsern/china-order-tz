import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ shipment: string }> };

/** GET /api/admin/shipments/[shipment]/tracking */
export async function GET(_request: Request, context: RouteContext) {
  const { shipment } = await context.params;
  return proxyAdminApiRequest(`/shipments/${encodeURIComponent(shipment)}/tracking`, {
    method: "GET",
  });
}

/** POST /api/admin/shipments/[shipment]/tracking */
export async function POST(request: Request, context: RouteContext) {
  const { shipment } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }
  return proxyAdminApiRequest(`/shipments/${encodeURIComponent(shipment)}/tracking`, {
    method: "POST",
    body,
  });
}
