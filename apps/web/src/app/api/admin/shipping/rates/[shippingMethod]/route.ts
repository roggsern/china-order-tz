import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function PUT(
  request: Request,
  context: { params: Promise<{ shippingMethod: string }> },
) {
  const { shippingMethod } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  return proxyAdminApiRequest(`/shipping/rates/${encodeURIComponent(shippingMethod)}`, {
    method: "PUT",
    body,
  });
}
