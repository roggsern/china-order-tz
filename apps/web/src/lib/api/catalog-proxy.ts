import { getServerApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

export function resolveCatalogProxyApiUrl(): string | null {
  return getServerApiUrl() || null;
}

export function catalogProductShowUpstreamUrl(apiUrl: string, slug: string): string {
  return `${apiUrl}/api/v1/products/${encodeURIComponent(slug.trim())}`;
}

export function catalogProductCheckoutSummaryUpstreamUrl(apiUrl: string, slug: string): string {
  return `${apiUrl}/api/v1/products/${encodeURIComponent(slug.trim())}/checkout-summary`;
}

export function catalogProductConfigurationUpstreamUrl(
  apiUrl: string,
  slug: string,
  search = "",
): string {
  const base = `${apiUrl}/api/v1/products/${encodeURIComponent(slug.trim())}/configuration`;
  return search ? `${base}?${search}` : base;
}

export function catalogProductQuoteUpstreamUrl(apiUrl: string, slug: string): string {
  return `${apiUrl}/api/v1/products/${encodeURIComponent(slug.trim())}/quote`;
}

export async function proxyCatalogJsonResponse(upstream: Response): Promise<NextResponse> {
  const payload = await upstream.json().catch(() => ({
    success: false,
    message: "Invalid upstream response.",
  }));

  return NextResponse.json(payload, { status: upstream.status });
}

export async function proxyCatalogProductShow(slug: string): Promise<NextResponse> {
  const trimmedSlug = slug.trim();

  if (!trimmedSlug) {
    return NextResponse.json(
      { success: false, message: "Product slug is required." },
      { status: 422 },
    );
  }

  const apiUrl = resolveCatalogProxyApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const upstream = await fetch(catalogProductShowUpstreamUrl(apiUrl, trimmedSlug), {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  return proxyCatalogJsonResponse(upstream);
}

export async function proxyCatalogProductCheckoutSummary(slug: string): Promise<NextResponse> {
  const trimmedSlug = slug.trim();

  if (!trimmedSlug) {
    return NextResponse.json(
      { success: false, message: "Product slug is required." },
      { status: 422 },
    );
  }

  const apiUrl = resolveCatalogProxyApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const upstream = await fetch(catalogProductCheckoutSummaryUpstreamUrl(apiUrl, trimmedSlug), {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  return proxyCatalogJsonResponse(upstream);
}

export async function proxyCatalogProductConfiguration(
  slug: string,
  request: Request,
): Promise<NextResponse> {
  const trimmedSlug = slug.trim();

  if (!trimmedSlug) {
    return NextResponse.json(
      { success: false, message: "Product slug is required." },
      { status: 422 },
    );
  }

  const apiUrl = resolveCatalogProxyApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const inbound = new URL(request.url);
  const upstreamUrl = new URL(catalogProductConfigurationUpstreamUrl(apiUrl, trimmedSlug));
  inbound.searchParams.forEach((value, key) => {
    if (key !== "slug") {
      upstreamUrl.searchParams.append(key, value);
    }
  });

  const upstream = await fetch(upstreamUrl.toString(), {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  return proxyCatalogJsonResponse(upstream);
}

export async function proxyCatalogProductQuote(slug: string, request: Request): Promise<NextResponse> {
  const trimmedSlug = slug.trim();

  if (!trimmedSlug) {
    return NextResponse.json(
      { success: false, message: "Product slug is required." },
      { status: 422 },
    );
  }

  const apiUrl = resolveCatalogProxyApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  const upstream = await fetch(catalogProductQuoteUpstreamUrl(apiUrl, trimmedSlug), {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  return proxyCatalogJsonResponse(upstream);
}

/** Client-side BFF path for product show — static route avoids [slug] compilation gaps. */
export function buildCatalogProductShowBffPath(slug: string): string {
  const trimmedSlug = slug.trim();
  const params = new URLSearchParams({ slug: trimmedSlug });
  return `/api/catalog/products?${params.toString()}`;
}

/** Client-side BFF path for checkout validation summary (listing-grade, not PDP). */
export function buildCatalogProductCheckoutSummaryBffPath(slug: string): string {
  const trimmedSlug = slug.trim();
  const params = new URLSearchParams({ slug: trimmedSlug });
  return `/api/catalog/products/checkout-summary?${params.toString()}`;
}

/** Client-side BFF path for product configuration schema. */
export function buildCatalogProductConfigurationBffPath(
  slug: string,
  query?: URLSearchParams,
): string {
  const params = new URLSearchParams({ slug: slug.trim() });
  query?.forEach((value, key) => {
    if (key !== "slug") {
      params.set(key, value);
    }
  });
  return `/api/catalog/products/configuration?${params.toString()}`;
}

/** Client-side BFF path for product quote. */
export function buildCatalogProductQuoteBffPath(slug: string): string {
  const params = new URLSearchParams({ slug: slug.trim() });
  return `/api/catalog/products/quote?${params.toString()}`;
}

/** Storefront product detail path (static route + slug query param). */
export function buildStorefrontProductDetailPath(slug: string): string {
  const params = new URLSearchParams({ slug: slug.trim() });
  return `/products/detail?${params.toString()}`;
}

/** Match /products/{slug} excluding listing and static detail route. */
export function parseStorefrontProductSlug(pathname: string): string | null {
  const match = pathname.match(/^\/products\/([^/]+)\/?$/);

  if (!match) {
    return null;
  }

  const slug = decodeURIComponent(match[1]);

  if (!slug || slug === "detail") {
    return null;
  }

  return slug;
}

/** Match /api/catalog/products/{slug} excluding nested configuration/quote paths. */
export function parseCatalogProductSlug(pathname: string): string | null {
  const match = pathname.match(/^\/api\/catalog\/products\/([^/]+)\/?$/);

  if (!match) {
    return null;
  }

  const slug = decodeURIComponent(match[1]);

  if (!slug || slug === "configuration" || slug === "quote") {
    return null;
  }

  return slug;
}

export function parseCatalogProductConfigurationSlug(pathname: string): string | null {
  const match = pathname.match(/^\/api\/catalog\/products\/([^/]+)\/configuration\/?$/);

  if (!match) {
    return null;
  }

  const slug = decodeURIComponent(match[1]);
  return slug || null;
}

export function parseCatalogProductQuoteSlug(pathname: string): string | null {
  const match = pathname.match(/^\/api\/catalog\/products\/([^/]+)\/quote\/?$/);

  if (!match) {
    return null;
  }

  const slug = decodeURIComponent(match[1]);
  return slug || null;
}

const CATALOG_PRODUCT_SLUG_HEADER = "x-catalog-product-slug";

/** Resolve product slug from query, pathname, or middleware rewrite header. */
export function resolveCatalogProductSlugFromRequest(request: Request): string | null {
  const url = new URL(request.url);

  const fromQuery = url.searchParams.get("slug")?.trim();
  if (fromQuery) {
    return fromQuery;
  }

  const fromPath = parseCatalogProductSlug(url.pathname);
  if (fromPath) {
    return fromPath;
  }

  const fromHeader = request.headers.get(CATALOG_PRODUCT_SLUG_HEADER)?.trim();
  if (fromHeader) {
    return fromHeader;
  }

  return null;
}

export function catalogProductSlugRewriteHeader(slug: string): Record<string, string> {
  return { [CATALOG_PRODUCT_SLUG_HEADER]: slug };
}
