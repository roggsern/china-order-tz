import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const query = url.searchParams.toString();

  return proxyCustomerApiRequest(
    request,
    `/payments/return-context${query ? `?${query}` : ""}`,
    { method: "GET" },
  );
}
