import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** PATCH /api/admin/fulfillments/bulk-assignment → Laravel PATCH .../bulk-assignment */
export async function PATCH(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest("/fulfillments/bulk-assignment", {
    method: "PATCH",
    body,
  });
}
