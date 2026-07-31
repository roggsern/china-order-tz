import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();
  if (!trimmed) {
    return Response.json({ success: false, message: "Store id is required." }, { status: 422 });
  }
  return proxyAdminApiRequest(`/stores/${encodeURIComponent(trimmed)}/team`, { method: "GET" });
}

export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();
  if (!trimmed) {
    return Response.json({ success: false, message: "Store id is required." }, { status: 422 });
  }
  const body = await request.json();
  return proxyAdminApiRequest(`/stores/${encodeURIComponent(trimmed)}/team`, {
    method: "POST",
    body,
  });
}
