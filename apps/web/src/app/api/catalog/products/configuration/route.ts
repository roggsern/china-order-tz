import {
  proxyCatalogProductConfiguration,
  resolveCatalogProductSlugFromRequest,
} from "@/lib/api/catalog-proxy";

/** GET /api/catalog/products/configuration?slug={slug} */
export async function GET(request: Request) {
  const slug = resolveCatalogProductSlugFromRequest(request) ?? "";
  return proxyCatalogProductConfiguration(slug, request);
}
