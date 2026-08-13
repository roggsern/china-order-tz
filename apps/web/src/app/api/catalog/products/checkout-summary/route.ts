import { NextResponse } from "next/server";
import {
  proxyCatalogProductCheckoutSummary,
  resolveCatalogProductSlugFromRequest,
} from "@/lib/api/catalog-proxy";

/** GET /api/catalog/products/checkout-summary?slug={slug} */
export async function GET(request: Request) {
  const slug = resolveCatalogProductSlugFromRequest(request);

  if (!slug) {
    return NextResponse.json(
      { success: false, message: "Product slug is required." },
      { status: 422 },
    );
  }

  return proxyCatalogProductCheckoutSummary(slug);
}
