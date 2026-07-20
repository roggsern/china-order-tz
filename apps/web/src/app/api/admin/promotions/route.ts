import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["search", "status", "type", "page", "per_page"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }
  return proxyAdminApiRequest("/promotions", { method: "GET", searchParams });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest("/promotions", { method: "POST", body });
}
