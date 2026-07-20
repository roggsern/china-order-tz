import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ returnRequest: string }> };

/** PATCH /api/admin/returns/[returnRequest]/status → Laravel PATCH .../status */
export async function PATCH(request: Request, context: RouteContext) {
  const { returnRequest } = await context.params;
  const trimmed = returnRequest.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Return request id is required." },
      { status: 422 },
    );
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/returns/${encodeURIComponent(trimmed)}/status`, {
    method: "PATCH",
    body,
  });
}
