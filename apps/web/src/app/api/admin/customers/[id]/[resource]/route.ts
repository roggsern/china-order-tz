import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

const ALLOWED = new Set([
  "orders",
  "payments",
  "shipments",
  "returns",
  "addresses",
  "timeline",
  "notes",
]);

type Params = { params: Promise<{ id: string; resource: string }> };

export async function GET(request: Request, { params }: Params) {
  const { id, resource } = await params;
  if (!ALLOWED.has(resource)) {
    return Response.json({ success: false, message: "Not found." }, { status: 404 });
  }

  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["page", "per_page"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }

  return proxyAdminApiRequest(`/customers/${id}/${resource}`, { method: "GET", searchParams });
}

export async function POST(request: Request, { params }: Params) {
  const { id, resource } = await params;
  if (resource !== "notes" && resource !== "tags") {
    return Response.json({ success: false, message: "Not found." }, { status: 404 });
  }
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest(`/customers/${id}/${resource}`, { method: "POST", body });
}
