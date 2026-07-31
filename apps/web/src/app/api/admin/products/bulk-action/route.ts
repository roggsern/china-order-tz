import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** POST /api/admin/products/bulk-action → Laravel POST /api/v1/admin/products/bulk-action */
export async function POST(request: Request) {
  const body = await request.json();
  return proxyAdminApiRequest("/products/bulk-action", {
    method: "POST",
    body,
  });
}
