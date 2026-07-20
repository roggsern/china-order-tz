import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ session: string }>;
};

export async function POST(request: Request, context: RouteContext) {
  const { session } = await context.params;
  return proxyCustomerApiRequest(
    request,
    `/orders/from-checkout/${encodeURIComponent(session)}`,
    { method: "POST" },
  );
}
