import type { ResolvedHomepageContent } from "./get-homepage-content";
import {
  isLaunchAdvertisementPlacementVisible,
  isLaunchHeroSlideType,
  isStorefrontLaunchSectionEnabled,
  storefrontLaunchConfig,
  type StorefrontLaunchConfig,
} from "./storefront-launch.config";

/**
 * Applies launch-only storefront presentation filters on resolved homepage content.
 * CMS/seed records remain intact — only customer-visible output is trimmed.
 */
export function applyStorefrontLaunchPresentation(
  content: ResolvedHomepageContent,
  config: StorefrontLaunchConfig = storefrontLaunchConfig,
): ResolvedHomepageContent {
  return {
    ...content,
    heroSlides: content.heroSlides.filter((slide) =>
      isLaunchHeroSlideType(slide.type, config),
    ),
    advertisements: content.advertisements.filter((ad) =>
      isLaunchAdvertisementPlacementVisible(ad.placement, config),
    ),
    sponsors: isStorefrontLaunchSectionEnabled("trustedPartners", config)
      ? content.sponsors
      : [],
    flashDeals: isStorefrontLaunchSectionEnabled("flashDeals", config)
      ? content.flashDeals
      : [],
  };
}
