import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = { params: Promise<{ returnRequest: string }> };

/** POST /api/admin/returns/[returnRequest]/refund → Laravel POST .../refund */
export async function POST(request: Request, context: RouteContext) {
  const { returnRequest } = await context.params;
  const trimmed = returnRequest.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Return request id is required." },
      { status: 422 },
    );
  }

  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  return proxyAdminApiRequest(`/returns/${encodeURIComponent(trimmed)}/refund`, {
    method: "POST",
    body,
  });
}
