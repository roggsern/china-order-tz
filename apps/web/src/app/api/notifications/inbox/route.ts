import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** GET /api/notifications/inbox → Laravel GET /api/v1/notifications */
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const upstream = new URLSearchParams();
  for (const key of ["page", "per_page"] as const) {
    const value = searchParams.get(key);
    if (value) upstream.set(key, value);
  }
  const qs = upstream.toString();
  return proxyCustomerApiRequest(
    request,
    `/notifications${qs ? `?${qs}` : ""}`,
    { method: "GET" },
  );
}
