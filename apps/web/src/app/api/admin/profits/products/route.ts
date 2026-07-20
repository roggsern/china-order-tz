import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["from", "to", "limit", "low_margin_threshold"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }

  return proxyAdminApiRequest("/profits/products", { method: "GET", searchParams });
}
