import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ orderNumber: string }>;
};

/**
 * BFF proxy for Laravel POST /api/v1/orders/{order}/payments
 *
 * Must live under [orderNumber] (same dynamic segment name as
 * app/api/orders/[orderNumber]/route.ts) — Next.js rejects sibling folders
 * that use different slug names at the same path depth.
 */
export async function POST(request: Request, context: RouteContext) {
  const { orderNumber } = await context.params;
  const trimmed = orderNumber?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Order id is required." },
      { status: 422 },
    );
  }

  return proxyCustomerApiRequest(request, `/orders/${encodeURIComponent(trimmed)}/payments`, {
    method: "POST",
  });
}
