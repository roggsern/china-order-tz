import {
  proxyCatalogProductQuote,
  resolveCatalogProductSlugFromRequest,
} from "@/lib/api/catalog-proxy";

/** POST /api/catalog/products/quote?slug={slug} */
export async function POST(request: Request) {
  const slug = resolveCatalogProductSlugFromRequest(request) ?? "";
  return proxyCatalogProductQuote(slug, request);
}
