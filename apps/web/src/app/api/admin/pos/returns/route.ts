import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const qs = new URL(request.url).searchParams.toString();
  return proxyAdminApiRequest(`/pos/returns${qs ? `?${qs}` : ""}`, { method: "GET" });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/pos/returns", { method: "POST", body });
}
