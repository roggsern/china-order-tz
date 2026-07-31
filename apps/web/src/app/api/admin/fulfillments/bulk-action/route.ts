import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** POST /api/admin/fulfillments/bulk-action → Laravel POST /api/v1/admin/fulfillments/bulk-action */
export async function POST(request: Request) {
  const body = await request.json();
  return proxyAdminApiRequest("/fulfillments/bulk-action", {
    method: "POST",
    body,
  });
}
