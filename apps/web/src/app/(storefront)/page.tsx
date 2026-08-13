import { Suspense } from "react";
import { HeroCarousel } from "@/components/home/commercial/HeroCarousel";
import { HomepageAdRail } from "@/components/home/commercial/HomepageAdBanner";
import { SponsorPartners } from "@/components/home/commercial/SponsorPartners";
import { FlashDeals } from "@/components/home/commercial/FlashDeals";
import { FeaturedCollections } from "@/components/home/commercial/FeaturedCollections";
import { ShopByStore } from "@/components/home/commercial/ShopByStore";
import { NewArrivalsSplit } from "@/components/home/commercial/NewArrivalsSplit";
import { BestSellers } from "@/components/home/commercial/BestSellers";
import { CommercialWhyChooseUs } from "@/components/home/commercial/CommercialWhyChooseUs";
import { TrustIndicators } from "@/components/home/commercial/TrustIndicators";
import { FeaturedProducts } from "@/components/home/FeaturedProducts";
import { ProductGridSkeleton } from "@/components/catalog/ProductGridSkeleton";
import {
  getHomepageContent,
  getAdsByPlacement,
  isStorefrontLaunchSectionEnabled,
} from "@/lib/content/homepage";
import {
  getHomeBestSellers,
  getHomeNewArrivalsByOrigin,
} from "@/lib/catalog/home-catalog";
import { mergeTzStoreMedia } from "@/lib/catalog/merge-tz-store-media";
import { getTzStores } from "@/lib/api/tz-stores";

async function CommercialNewArrivals() {
  const content = await getHomepageContent();
  const [chinaProducts, tzProducts] = await Promise.all([
    content.newArrivalsChina != null
      ? Promise.resolve(content.newArrivalsChina)
      : getHomeNewArrivalsByOrigin("china", 4).catch(() => []),
    content.newArrivalsTz != null
      ? Promise.resolve(content.newArrivalsTz)
      : getHomeNewArrivalsByOrigin("tz", 4).catch(() => []),
  ]);

  return (
    <NewArrivalsSplit
      chinaProducts={chinaProducts}
      tzProducts={tzProducts}
      copy={content.sections.newArrivals}
    />
  );
}

async function CommercialBestSellers() {
  const content = await getHomepageContent();
  const products =
    content.bestSellers ?? (await getHomeBestSellers(8).catch(() => []));
  return <BestSellers products={products} copy={content.sections.bestSellers} />;
}

async function CommercialShopByStore() {
  const content = await getHomepageContent();
  const liveStores = await getTzStores().catch(() => []);
  const preferred = content.shopByStores?.length ? content.shopByStores : liveStores;
  const stores = mergeTzStoreMedia(preferred, liveStores);
  return <ShopByStore stores={stores} copy={content.sections.shopByStore} />;
}

async function CommercialFeaturedProducts() {
  const content = await getHomepageContent();
  return (
    <FeaturedProducts
      products={content.featuredProducts}
      copy={content.featuredProductsCopy}
    />
  );
}

export default async function Home() {
  const content = await getHomepageContent();
  const homepageBanners = getAdsByPlacement(content.advertisements, "homepage_banner");
  const midPageAds = getAdsByPlacement(content.advertisements, "mid_page");

  return (
    <>
      <HeroCarousel slides={content.heroSlides} />

      {homepageBanners.length > 0 &&
      isStorefrontLaunchSectionEnabled("flashPromotions") ? (
        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <HomepageAdRail ads={homepageBanners} />
        </div>
      ) : null}

      {isStorefrontLaunchSectionEnabled("flashDeals") ? (
        <FlashDeals deals={content.flashDeals} copy={content.sections.flashDeals} />
      ) : null}

      <FeaturedCollections
        collections={content.collections}
        copy={content.sections.collections}
      />

      <Suspense
        fallback={
          <section className="bg-zinc-50 py-16 sm:py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="h-8 w-56 animate-pulse rounded bg-zinc-100" />
              <div className="mt-10">
                <ProductGridSkeleton count={4} />
              </div>
            </div>
          </section>
        }
      >
        <CommercialShopByStore />
      </Suspense>

      <Suspense
        fallback={
          <section className="bg-zinc-50 py-20 sm:py-28">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="h-8 w-56 animate-pulse rounded bg-zinc-100" />
              <div className="mt-10">
                <ProductGridSkeleton count={8} />
              </div>
            </div>
          </section>
        }
      >
        <CommercialFeaturedProducts />
      </Suspense>

      {midPageAds.length > 0 && isStorefrontLaunchSectionEnabled("midPageAds") ? (
        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <HomepageAdRail ads={midPageAds} />
        </div>
      ) : null}

      <Suspense
        fallback={
          <section className="bg-white py-16 sm:py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="h-8 w-56 animate-pulse rounded bg-zinc-100" />
              <div className="mt-10">
                <ProductGridSkeleton count={8} />
              </div>
            </div>
          </section>
        }
      >
        <CommercialNewArrivals />
      </Suspense>

      <Suspense
        fallback={
          <section className="bg-zinc-50 py-16 sm:py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
              <div className="h-8 w-56 animate-pulse rounded bg-zinc-100" />
              <div className="mt-10">
                <ProductGridSkeleton count={8} />
              </div>
            </div>
          </section>
        }
      >
        <CommercialBestSellers />
      </Suspense>

      {isStorefrontLaunchSectionEnabled("trustedPartners") ? (
        <SponsorPartners sponsors={content.sponsors} copy={content.sections.sponsors} />
      ) : null}

      <CommercialWhyChooseUs
        items={content.whyChooseUs}
        copy={content.sections.whyChooseUs}
      />

      <TrustIndicators items={content.trustIndicators} copy={content.sections.trust} />
    </>
  );
}
