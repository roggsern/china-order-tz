/**
 * CMS Navigation Shell — storefront API client.
 * Proxies via Next BFF in the browser; hits Laravel directly on the server.
 */

import { getApiUrl } from "@/lib/config/env";

export type CmsNavAudience = "guest" | "authenticated" | "admin_preview";

export type CmsNavCommerceContext = "GLOBAL" | "CHINA_IMPORT" | "TZ_LOCAL";

export type CmsNavType = "PRIMARY" | "FOOTER" | "MOBILE" | "UTILITY";

export type CmsNavCta = {
  type: string;
  label: string | null;
  value: string | null;
  url: string | null;
};

export type CmsNavJourney = {
  code: string;
  engine: string;
  label: string;
};

export type CmsNavMegaMenu = {
  engine: string;
  journey: string;
  categories?: unknown[];
  stores?: Array<{
    id: string;
    name: string;
    slug: string;
    logo_url?: string | null;
    storefront_featured?: boolean;
  }>;
};

export type CmsNavigationItem = {
  id: string;
  title: string;
  icon: string | null;
  position: number;
  visibility: string;
  item_type: "LINK" | "JOURNEY" | "MEGA_MENU" | "GROUP" | string;
  target_type: string | null;
  target_value: string | null;
  children?: CmsNavigationItem[];
  cta?: CmsNavCta | null;
  journey?: CmsNavJourney;
  mega_menu?: CmsNavMegaMenu;
};

export type CmsNavigationShellPayload = {
  commerce_context: string;
  navigation_type: string;
  shell: {
    id: string;
    name: string;
    slug: string;
    is_default: boolean;
    status: string;
  } | null;
  campaign: {
    id: string;
    name: string;
    slug: string;
    priority: number;
  } | null;
  items: CmsNavigationItem[];
};

export type CmsNavigationAllPayload = {
  commerce_context: string;
  campaign: {
    id: string;
    name: string;
    slug: string;
    priority: number;
  } | null;
  shells: Partial<Record<CmsNavType, CmsNavigationShellPayload>>;
};

export class CmsNavigationApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CmsNavigationApiError";
  }
}

function isServerRuntime(): boolean {
  return typeof window === "undefined";
}

function buildUrl(searchParams?: URLSearchParams): string {
  const query = searchParams?.toString();
  if (isServerRuntime()) {
    const apiUrl = getApiUrl();
    if (!apiUrl) throw new CmsNavigationApiError("API URL is not configured.");
    return `${apiUrl}/api/v1/storefront/navigation${query ? `?${query}` : ""}`;
  }
  return `/api/storefront/navigation${query ? `?${query}` : ""}`;
}

export type GetCmsNavigationParams = {
  commerceContext: CmsNavCommerceContext;
  navigationType?: CmsNavType;
  audience?: CmsNavAudience;
  hydrateMegaMenus?: boolean;
};

export async function getCmsNavigation(
  params: GetCmsNavigationParams,
): Promise<CmsNavigationShellPayload | CmsNavigationAllPayload> {
  const search = new URLSearchParams();
  search.set("commerce_context", params.commerceContext);
  if (params.navigationType) search.set("navigation_type", params.navigationType);
  if (params.audience) search.set("audience", params.audience);
  if (params.hydrateMegaMenus === false) search.set("hydrate_mega_menus", "0");
  if (params.hydrateMegaMenus === true) search.set("hydrate_mega_menus", "1");

  const response = await fetch(buildUrl(search), {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: CmsNavigationShellPayload | CmsNavigationAllPayload;
  };

  if (!response.ok || !payload.data) {
    throw new CmsNavigationApiError(
      payload.message ?? "Unable to load storefront navigation.",
      response.status,
    );
  }

  return payload.data;
}

export function isCmsNavigationAllPayload(
  data: CmsNavigationShellPayload | CmsNavigationAllPayload,
): data is CmsNavigationAllPayload {
  return "shells" in data && data.shells !== undefined;
}
