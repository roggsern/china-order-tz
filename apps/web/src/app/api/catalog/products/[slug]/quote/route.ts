import { proxyCatalogProductQuote } from "@/lib/api/catalog-proxy";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** POST /api/catalog/products/[slug]/quote → Laravel ResolvePrice quote */
export async function POST(request: Request, context: RouteContext) {
  const { slug } = await context.params;
  return proxyCatalogProductQuote(slug, request);
}
