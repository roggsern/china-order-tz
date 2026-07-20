import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ fulfillment: string }>;
};

/** PATCH /api/admin/fulfillments/[fulfillment]/status → Laravel PATCH .../status */
export async function PATCH(request: Request, context: RouteContext) {
  const { fulfillment } = await context.params;

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/fulfillments/${encodeURIComponent(fulfillment)}/status`,
    { method: "PATCH", body },
  );
}
