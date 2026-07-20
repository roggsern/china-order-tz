import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/growth/campaigns", { method: "GET" });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/growth/campaigns", { method: "POST", body });
}
