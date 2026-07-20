import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ id: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { id } = await params;
  return proxyAdminApiRequest(`/promotions/${id}`, { method: "GET" });
}

export async function PUT(request: Request, { params }: Params) {
  const { id } = await params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest(`/promotions/${id}`, { method: "PUT", body });
}
