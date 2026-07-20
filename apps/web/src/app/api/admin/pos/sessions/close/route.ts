import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(request: Request) {
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyAdminApiRequest("/pos/sessions/close", { method: "POST", body });
}
