import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/growth/offers", { method: "GET" });
}
