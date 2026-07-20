import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ returnRequest: string }> };

/** GET /api/admin/returns/[returnRequest] → Laravel GET /api/v1/admin/returns/{returnRequest} */
export async function GET(_request: Request, context: RouteContext) {
  const { returnRequest } = await context.params;
  const trimmed = returnRequest.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Return request id is required." },
      { status: 422 },
    );
  }
  return proxyAdminApiRequest(`/returns/${encodeURIComponent(trimmed)}`, {
    method: "GET",
  });
}
