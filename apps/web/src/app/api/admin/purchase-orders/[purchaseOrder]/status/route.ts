import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function PATCH(
  request: Request,
  context: { params: Promise<{ purchaseOrder: string }> },
) {
  const { purchaseOrder } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  return proxyAdminApiRequest(`/purchase-orders/${purchaseOrder}/status`, { method: "PATCH", body });
}
