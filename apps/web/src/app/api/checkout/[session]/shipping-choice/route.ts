import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ session: string }>;
};

export async function POST(request: Request, context: RouteContext) {
  const { session } = await context.params;
  const trimmed = session?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Checkout session id is required." },
      { status: 422 },
    );
  }

  return proxyCustomerApiRequest(
    request,
    `/checkout/${encodeURIComponent(trimmed)}/shipping-choice`,
    { method: "POST" },
  );
}
