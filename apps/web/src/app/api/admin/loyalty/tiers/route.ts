import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/loyalty/tiers", { method: "GET" });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/loyalty/tiers", { method: "POST", body });
}
