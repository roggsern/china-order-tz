import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/account/addresses", { method: "GET" });
}

export async function POST(request: Request) {
  return proxyCustomerApiRequest(request, "/account/addresses", { method: "POST" });
}
