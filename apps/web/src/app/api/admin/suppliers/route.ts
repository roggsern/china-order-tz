import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["page", "per_page", "search", "is_active"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }

  return proxyAdminApiRequest("/suppliers", { method: "GET", searchParams });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  return proxyAdminApiRequest("/suppliers", { method: "POST", body });
}
