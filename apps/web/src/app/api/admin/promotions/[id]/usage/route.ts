import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ id: string }> };

export async function GET(_request: Request, { params }: Params) {
  const { id } = await params;
  return proxyAdminApiRequest(`/promotions/${id}/usage`, { method: "GET" });
}
