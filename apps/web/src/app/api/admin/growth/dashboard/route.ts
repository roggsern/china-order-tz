import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/growth/dashboard", { method: "GET" });
}
