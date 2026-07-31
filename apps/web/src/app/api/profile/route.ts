import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/profile", { method: "GET" });
}

export async function PATCH(request: Request) {
  return proxyCustomerApiRequest(request, "/profile", { method: "PATCH" });
}
