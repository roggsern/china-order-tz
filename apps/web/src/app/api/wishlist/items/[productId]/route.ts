import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ productId: string }>;
};

export async function DELETE(request: Request, context: RouteContext) {
  const { productId } = await context.params;
  return proxyCustomerApiRequest(
    request,
    `/wishlist/items/${encodeURIComponent(productId)}`,
    { method: "DELETE" },
  );
}
