import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = { params: Promise<{ orderNumber: string }> };

/** POST /api/orders/[orderNumber]/returns → Laravel POST /api/v1/orders/{order}/returns */
export async function POST(request: Request, context: RouteContext) {
  const { orderNumber } = await context.params;
  const trimmed = orderNumber.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Order number is required." },
      { status: 422 },
    );
  }

  return proxyCustomerApiRequest(
    request,
    `/orders/${encodeURIComponent(trimmed)}/returns`,
    { method: "POST" },
  );
}
