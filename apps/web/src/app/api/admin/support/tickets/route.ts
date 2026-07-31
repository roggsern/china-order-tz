import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  return proxyAdminApiRequest("/support/tickets", { method: "GET", searchParams });
}
