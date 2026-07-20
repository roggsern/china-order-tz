import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = { params: Promise<{ id: string }> };

/** GET /api/returns/[id] → Laravel GET /api/v1/returns/{returnRequest} */
export async function GET(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Return id is required." },
      { status: 422 },
    );
  }

  return proxyCustomerApiRequest(
    request,
    `/returns/${encodeURIComponent(trimmed)}`,
    { method: "GET" },
  );
}
