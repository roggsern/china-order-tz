import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ session: string }>;
};

export async function GET(request: Request, context: RouteContext) {
  const { session } = await context.params;
  return proxyCustomerApiRequest(request, `/checkout/${encodeURIComponent(session)}`, {
    method: "GET",
  });
}

export async function DELETE(request: Request, context: RouteContext) {
  const { session } = await context.params;
  return proxyCustomerApiRequest(request, `/checkout/${encodeURIComponent(session)}`, {
    method: "DELETE",
  });
}
