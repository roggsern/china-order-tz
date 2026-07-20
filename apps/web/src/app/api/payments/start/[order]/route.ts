import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ order: string }>;
};

export async function POST(request: Request, context: RouteContext) {
  const { order } = await context.params;
  return proxyCustomerApiRequest(request, `/payments/start/${encodeURIComponent(order)}`, {
    method: "POST",
  });
}
