import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/notification-templates */
export async function GET() {
  return proxyAdminApiRequest("/notification-templates", { method: "GET" });
}

/** POST /api/admin/notification-templates */
export async function POST(request: Request) {
  return proxyAdminApiRequest("/notification-templates", {
    method: "POST",
    body: await request.json(),
  });
}
