import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function PATCH(request: Request) {
  return proxyCustomerApiRequest(request, "/profile/address", { method: "PATCH" });
}
