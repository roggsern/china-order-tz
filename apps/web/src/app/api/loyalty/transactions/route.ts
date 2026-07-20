import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const upstream = new URLSearchParams();
  for (const key of ["page", "per_page"] as const) {
    const value = searchParams.get(key);
    if (value) upstream.set(key, value);
  }
  const qs = upstream.toString();
  return proxyCustomerApiRequest(request, `/loyalty/transactions${qs ? `?${qs}` : ""}`, {
    method: "GET",
  });
}
