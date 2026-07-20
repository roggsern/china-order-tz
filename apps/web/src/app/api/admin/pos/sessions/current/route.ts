import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/pos/sessions/current", { method: "GET" });
}
