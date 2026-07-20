import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ item: string }>;
};

export async function PUT(request: Request, context: RouteContext) {
  const { item } = await context.params;
  return proxyCustomerApiRequest(request, `/cart/items/${encodeURIComponent(item)}`, {
    method: "PUT",
  });
}

export async function PATCH(request: Request, context: RouteContext) {
  const { item } = await context.params;
  return proxyCustomerApiRequest(request, `/cart/items/${encodeURIComponent(item)}`, {
    method: "PATCH",
  });
}

export async function DELETE(request: Request, context: RouteContext) {
  const { item } = await context.params;
  return proxyCustomerApiRequest(request, `/cart/items/${encodeURIComponent(item)}`, {
    method: "DELETE",
  });
}
