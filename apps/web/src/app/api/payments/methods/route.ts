import { NextResponse } from "next/server";
import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/payments/methods", {
    method: "GET",
  });
}
