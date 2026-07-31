import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/stores → Laravel GET /api/v1/admin/stores */
export async function GET() {
  return proxyAdminApiRequest("/stores", { method: "GET" });
}

/** POST /api/admin/stores → Laravel POST /api/v1/admin/stores */
export async function POST(request: Request) {
  const body = await request.json();
  return proxyAdminApiRequest("/stores", { method: "POST", body });
}
