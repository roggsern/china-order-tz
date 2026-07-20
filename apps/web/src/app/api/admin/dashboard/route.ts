import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/dashboard → Laravel GET /api/v1/admin/dashboard */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["from", "to"]);
  return proxyAdminApiRequest("/dashboard", { method: "GET", searchParams });
}
