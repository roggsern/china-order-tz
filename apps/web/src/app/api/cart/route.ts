import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/cart", { method: "GET" });
}

export async function DELETE(request: Request) {
  return proxyCustomerApiRequest(request, "/cart", { method: "DELETE" });
}
