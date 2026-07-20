import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["customer_id", "loyalty_number", "search"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }
  return proxyAdminApiRequest("/pos/loyalty/lookup", { method: "GET", searchParams });
}
