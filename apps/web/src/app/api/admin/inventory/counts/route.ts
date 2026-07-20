import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  const storeId = url.searchParams.get("store_id");
  if (storeId) searchParams.set("store_id", storeId);
  return proxyAdminApiRequest("/inventory/counts", { method: "GET", searchParams });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/inventory/counts", { method: "POST", body });
}
