import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function DELETE(request: Request) {
  return proxyCustomerApiRequest(request, "/cart/clear", { method: "DELETE" });
}
