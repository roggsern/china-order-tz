"use client";

import { useMemo } from "react";
import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { HomepageAdRail } from "@/components/home/commercial/HomepageAdBanner";
import { OfficialLogoImage } from "@/components/branding/OfficialLogoImage";
import { useTzStores } from "@/lib/catalog/use-tz-stores";
import { filterActiveScheduled, getAdsByPlacement, homepageContentSeed, isLaunchAdvertisementPlacementVisible } from "@/lib/content/homepage";
import {
  buildCurrentReturnPath,
  resolveAuthEntryHref,
} from "@/lib/auth/return-url";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import {
  buildFooterBuyFromTzLinks,
  defaultFooterBuyFromTzLinks,
  FOOTER_BRAND_CREDIT,
  normalizeFooterColumn,
} from "@/lib/storefront/footer-content";
import { resolveStorefrontNavAudience } from "@/lib/storefront/navigation-policy";
import { useStorefrontNavigation } from "@/lib/storefront/use-storefront-navigation";

export function Footer() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const returnPath = buildCurrentReturnPath(pathname, searchParams.toString());
  const { isLoggedIn, isReady } = useCustomerSession();
  const audience = resolveStorefrontNavAudience({ isLoggedIn: isReady && isLoggedIn });
  const { navigation } = useStorefrontNavigation(audience);
  const { stores: liveTzStores } = useTzStores();

  const footerAds = useMemo(() => {
    const active = filterActiveScheduled(homepageContentSeed.advertisements);
    return getAdsByPlacement(active, "footer").filter((ad) =>
      isLaunchAdvertisementPlacementVisible(ad.placement),
    );
  }, []);

  const columns = navigation.footerColumns;

  const buyFromTzColumn = useMemo(() => {
    const cmsTz = columns.find(
      (col) =>
        col.key.toLowerCase().includes("tz") ||
        col.title.toLowerCase().includes("buy from tz") ||
        col.title.toLowerCase().includes("tanzania"),
    );

    const stores =
      navigation.footerTzStores.length > 0
        ? navigation.footerTzStores
        : liveTzStores.map((store) => ({
            id: store.id,
            name: store.name,
            slug: store.slug,
            logo_url: store.logo_url ?? null,
          }));

    const links =
      stores.length > 0
        ? buildFooterBuyFromTzLinks(stores)
        : defaultFooterBuyFromTzLinks();

    return {
      key: cmsTz?.key ?? "buyFromTz",
      title: cmsTz?.title ?? "Buy From TZ",
      links,
    };
  }, [columns, navigation.footerTzStores, liveTzStores]);

  const otherColumns = columns
    .filter((col) => col.key !== buyFromTzColumn.key)
    .map(normalizeFooterColumn);

  return (
    <footer id="contact" className="relative bg-zinc-950 text-zinc-400">
      <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#c9a227]/40 to-transparent" />

      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        {footerAds.length > 0 ? (
          <div className="mb-12">
            <HomepageAdRail ads={footerAds} compact />
          </div>
        ) : null}

        <div className="grid gap-12 lg:grid-cols-12 lg:gap-x-12 lg:gap-y-10">
          <div className="lg:col-span-3">
            <OfficialLogoImage variant="footer" height={72} />
            <p className="mt-5 max-w-sm text-sm leading-relaxed">
              Tanzania&apos;s premium platform for importing quality products directly from China.
              Trusted by shoppers and businesses nationwide.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-10 lg:col-span-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(12rem,1.6fr)_minmax(0,0.95fr)] lg:gap-x-12 xl:gap-x-16">
            {otherColumns.slice(0, 3).map((column) => {
              const isContactColumn = column.title.toLowerCase().includes("contact");

              return (
                <div
                  key={column.key}
                  className={
                    isContactColumn ? "min-w-0 lg:max-w-none lg:pr-2" : "min-w-0"
                  }
                >
                  <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
                    {column.title}
                  </h3>
                  <ul className="mt-4 space-y-3">
                    {column.links.map((link) => (
                      <li key={`${column.key}-${link.label}-${link.href}`}>
                        <Link
                          href={resolveAuthEntryHref(link.href, returnPath)}
                          className={`text-sm transition hover:text-white${
                            isContactColumn
                              ? " block max-w-full break-all sm:break-words lg:whitespace-nowrap"
                              : ""
                          }`}
                        >
                          {link.label}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
          </div>

          <div className="min-w-0 lg:col-span-3">
            <div>
              <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
                {buyFromTzColumn.title}
              </h3>
              <ul className="mt-4 space-y-3">
                {buyFromTzColumn.links.map((link) => (
                  <li key={`${buyFromTzColumn.key}-${link.label}-${link.href}`}>
                    <Link
                      href={resolveAuthEntryHref(link.href, returnPath)}
                      className="text-sm transition hover:text-white"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>

        <div className="mt-14 grid gap-4 border-t border-zinc-800/80 pt-8 text-center sm:grid-cols-3 sm:items-center sm:text-left">
          <p className="text-xs text-zinc-600">
            &copy; {new Date().getFullYear()} CHINA ORDER TZ. All rights reserved.
          </p>

          <div className="text-xs leading-relaxed text-zinc-600 sm:text-center">
            <p>{FOOTER_BRAND_CREDIT.line1}</p>
            <a
              href={FOOTER_BRAND_CREDIT.phoneHref}
              className="mt-0.5 inline-block transition hover:text-[#c9a227]"
            >
              {FOOTER_BRAND_CREDIT.phone}
            </a>
          </div>

          <div className="flex justify-center gap-6 text-xs text-zinc-600 sm:justify-end">
            <Link href="#" className="transition hover:text-[#c9a227]">
              Terms of Service
            </Link>
            <Link href="#" className="transition hover:text-[#c9a227]">
              Privacy Policy
            </Link>
            <Link href="#" className="transition hover:text-[#c9a227]">
              Cookies
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
