/**
 * CMS Homepage — storefront API client.
 * Proxies via Next BFF in the browser; hits Laravel directly on the server.
 */

import { getApiUrl } from "@/lib/config/env";

export type CmsHomepageCommerceContext = "GLOBAL" | "CHINA_IMPORT" | "TZ_LOCAL";

export type CmsHomepageCta = {
  type: string;
  label: string | null;
  value: string | null;
  url: string | null;
};

export type CmsHomepageMedia = {
  id: string;
  disk?: string;
  path?: string;
  filename?: string;
  mime?: string | null;
  alt_text?: string | null;
  url?: string | null;
};

export type CmsHomepageHeroSlide = {
  id: string;
  headline: string | null;
  subheadline: string | null;
  eyebrow_text: string | null;
  description: string | null;
  desktop_media?: CmsHomepageMedia | null;
  mobile_media?: CmsHomepageMedia | null;
  content_alignment?: string | null;
  text_theme?: string | null;
  primary_cta?: CmsHomepageCta | null;
  secondary_cta?: CmsHomepageCta | null;
  position: number;
};

export type CmsHomepageFeaturedItem = {
  item_type: string;
  id: string;
  data: Record<string, unknown>;
};

export type CmsHomepageFeaturedContent = {
  id: string;
  cms_homepage_section_id: string;
  title: string | null;
  subtitle: string | null;
  source_type: string;
  limit: number;
  sort_order?: string | null;
  display_style?: string | null;
  position: number;
  items?: CmsHomepageFeaturedItem[];
};

export type CmsHomepageSection = {
  id: string;
  cms_homepage_layout_id: string;
  section_type: string;
  title: string | null;
  subtitle: string | null;
  position: number;
  is_visible: boolean;
  configuration?: Record<string, unknown>;
  hero_slides?: CmsHomepageHeroSlide[];
  featured_contents?: CmsHomepageFeaturedContent[];
};

export type CmsHomepageLayout = {
  id: string;
  name: string;
  slug: string;
  commerce_context: string;
  status: string;
  is_default: boolean;
  sections: CmsHomepageSection[];
};

export type CmsHomepageCampaignMeta = {
  id: string;
  name: string;
  slug: string;
  priority: number;
  promotion_ids?: string[];
};

export type CmsHomepageMeta = {
  commerce_context: string;
  resolved_commerce_context?: string;
  allow_global_fallback?: boolean;
  used_global_fallback?: boolean;
  campaign?: CmsHomepageCampaignMeta | null;
  message?: string;
};

export type CmsHomepageResponse = {
  success: boolean;
  data: CmsHomepageLayout | null;
  meta: CmsHomepageMeta;
};

export class CmsHomepageApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CmsHomepageApiError";
  }
}

const DEFAULT_TIMEOUT_MS = 8_000;

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildUrl(searchParams?: URLSearchParams): string {
  const query = searchParams?.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) throw new CmsHomepageApiError("API URL is not configured.");
    return `${apiUrl}/api/v1/storefront/homepage${query ? `?${query}` : ""}`;
  }
  return `/api/storefront/homepage${query ? `?${query}` : ""}`;
}

export type GetCmsHomepageParams = {
  commerceContext?: CmsHomepageCommerceContext;
  allowGlobalFallback?: boolean;
  timeoutMs?: number;
};

/**
 * Fetch storefront homepage layout (campaign → default → optional GLOBAL fallback).
 */
export async function getCmsHomepage(
  params: GetCmsHomepageParams = {},
): Promise<CmsHomepageResponse> {
  const search = new URLSearchParams();
  search.set("commerce_context", params.commerceContext ?? "GLOBAL");
  if (params.allowGlobalFallback === false) {
    search.set("allow_global_fallback", "0");
  }

  const timeoutMs = params.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(buildUrl(search), {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: controller.signal,
    });

    const payload = (await response.json()) as CmsHomepageResponse & {
      message?: string;
    };

    if (!response.ok) {
      throw new CmsHomepageApiError(
        payload.message ?? "Unable to load CMS homepage.",
        response.status,
      );
    }

    if (!payload || typeof payload !== "object" || !("meta" in payload)) {
      throw new CmsHomepageApiError("Invalid CMS homepage response.", response.status);
    }

    return {
      success: Boolean(payload.success),
      data: payload.data ?? null,
      meta: payload.meta,
    };
  } catch (error) {
    if (error instanceof CmsHomepageApiError) {
      throw error;
    }
    if (error instanceof Error && error.name === "AbortError") {
      throw new CmsHomepageApiError(`CMS homepage request timed out after ${timeoutMs}ms.`, 408);
    }
    throw new CmsHomepageApiError(
      error instanceof Error ? error.message : "Unable to load CMS homepage.",
    );
  } finally {
    clearTimeout(timer);
  }
}
