import { proxyCatalogProductConfiguration } from "@/lib/api/catalog-proxy";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** GET /api/catalog/products/[slug]/configuration → Laravel configuration schema */
export async function GET(request: Request, context: RouteContext) {
  const { slug } = await context.params;
  return proxyCatalogProductConfiguration(slug, request);
}
