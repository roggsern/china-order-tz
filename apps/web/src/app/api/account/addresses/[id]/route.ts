import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

export async function PUT(request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyCustomerApiRequest(request, `/account/addresses/${encodeURIComponent(id)}`, {
    method: "PUT",
  });
}

export async function DELETE(request: Request, context: RouteContext) {
  const { id } = await context.params;
  return proxyCustomerApiRequest(request, `/account/addresses/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}
