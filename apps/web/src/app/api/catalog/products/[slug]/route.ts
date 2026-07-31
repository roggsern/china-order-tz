import { proxyCatalogProductShow } from "@/lib/api/catalog-proxy";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** GET /api/catalog/products/[slug] → Laravel product show */
export async function GET(_request: Request, context: RouteContext) {
  const { slug } = await context.params;
  return proxyCatalogProductShow(slug);
}
