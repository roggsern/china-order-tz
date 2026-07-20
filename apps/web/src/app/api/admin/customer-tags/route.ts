import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  const activeOnly = url.searchParams.get("active_only");
  if (activeOnly) searchParams.set("active_only", activeOnly);
  return proxyAdminApiRequest("/customer-tags", { method: "GET", searchParams });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/customer-tags", { method: "POST", body });
}
