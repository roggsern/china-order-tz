import type { AdvertisementPlacement, HeroSlideType } from "./types";

/**
 * Customer storefront launch presentation — hides pre-launch marketing surfaces
 * without deleting CMS entities, seed records, or backend models.
 */
export type StorefrontLaunchSectionKey =
  | "flashDeals"
  | "flashPromotions"
  | "trustedPartners"
  | "midPageAds"
  | "footerSponsorAds";

export type StorefrontLaunchConfig = {
  /** Hero carousel keeps only dual-journey slides for launch. */
  heroAllowedTypes: readonly HeroSlideType[];
  sections: Record<StorefrontLaunchSectionKey, boolean>;
};

const LAUNCH_HERO_TYPES = ["china", "tz"] as const satisfies readonly HeroSlideType[];

export const storefrontLaunchConfig: StorefrontLaunchConfig = {
  heroAllowedTypes: LAUNCH_HERO_TYPES,
  sections: {
    flashDeals: false,
    flashPromotions: false,
    trustedPartners: false,
    midPageAds: false,
    footerSponsorAds: false,
  },
};

export function isStorefrontLaunchSectionEnabled(
  key: StorefrontLaunchSectionKey,
  config: StorefrontLaunchConfig = storefrontLaunchConfig,
): boolean {
  return config.sections[key] ?? true;
}

export function isLaunchHeroSlideType(
  type: HeroSlideType,
  config: StorefrontLaunchConfig = storefrontLaunchConfig,
): boolean {
  return config.heroAllowedTypes.includes(type);
}

export function isLaunchAdvertisementPlacementVisible(
  placement: AdvertisementPlacement,
  config: StorefrontLaunchConfig = storefrontLaunchConfig,
): boolean {
  if (placement === "homepage_banner") {
    return isStorefrontLaunchSectionEnabled("flashPromotions", config);
  }
  if (placement === "mid_page") {
    return isStorefrontLaunchSectionEnabled("midPageAds", config);
  }
  if (placement === "footer") {
    return isStorefrontLaunchSectionEnabled("footerSponsorAds", config);
  }
  return true;
}
