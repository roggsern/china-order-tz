import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

export async function GET(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();
  if (!trimmed) {
    return Response.json({ success: false, message: "Store id is required." }, { status: 422 });
  }
  const { searchParams } = new URL(request.url);
  return proxyAdminApiRequest(`/stores/${encodeURIComponent(trimmed)}/dashboard`, {
    method: "GET",
    searchParams,
  });
}
