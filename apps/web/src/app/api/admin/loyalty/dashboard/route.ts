import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/loyalty/dashboard", { method: "GET" });
}
