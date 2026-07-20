"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { CountryFlag } from "@/components/storefront/CountryFlag";
import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import { useChinaStorefrontMenu } from "@/lib/catalog/use-china-storefront-menu";
import type { ApiCatalogCategory, ApiCatalogProductCard } from "@/lib/api/products";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";
import { ArrowRightIcon, ChevronDownIcon, GridIcon } from "./icons";

interface MegaMenuProps {
  mobile?: boolean;
  onNavigate?: () => void;
  linkClassName?: string;
  triggerLabel?: string;
  showHamburger?: boolean;
  showGridIcon?: boolean;
  showCountryFlag?: boolean;
  align?: "left" | "center";
}

const defaultTriggerClassName =
  "inline-flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-[13px] font-medium text-zinc-600 transition-all duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900";

function productImage(product: ApiCatalogProductCard): string | null {
  return product.primary_image?.url || product.primary_image?.path || null;
}

function categoryHref(slug: string): string {
  return `/products?origin=china&category=${encodeURIComponent(slug)}`;
}

function brandHref(brandSlug: string, categorySlug?: string): string {
  const params = new URLSearchParams({ origin: "china", brand: brandSlug });
  if (categorySlug) params.set("category", categorySlug);
  return `/products?${params.toString()}`;
}

/**
 * Order from China mega menu — Catalog Bible categories + China-import brands/products only.
 */
export function MegaMenu({
  mobile = false,
  onNavigate,
  linkClassName,
  triggerLabel = STOREFRONT_NAV_LABELS.orderFromChina,
  showHamburger = false,
  showGridIcon = false,
  showCountryFlag = false,
  align = "center",
}: MegaMenuProps) {
  const [activeSlug, setActiveSlug] = useState("");
  const [open, setOpen] = useState(false);
  const [expandedSlug, setExpandedSlug] = useState<string | null>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const menuId = useId();
  const { menu, isLoading } = useChinaStorefrontMenu(activeSlug || undefined);

  useEffect(() => {
    if (!open || mobile) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
      }
    };

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("mousedown", onPointerDown);

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.removeEventListener("mousedown", onPointerDown);
    };
  }, [open, mobile]);

  const categories = menu?.categories ?? [];
  const resolvedActiveSlug = activeSlug || menu?.active_category || categories[0]?.slug || "";

  const activeCategory = useMemo(() => {
    return categories.find((category) => category.slug === resolvedActiveSlug) ?? categories[0];
  }, [categories, resolvedActiveSlug]);

  const presentation = activeCategory
    ? enrichApiCategoryFromStatic({ slug: activeCategory.slug, name: activeCategory.name })
    : null;

  useEffect(() => {
    if (!activeSlug && categories[0]?.slug) {
      setActiveSlug(categories[0].slug);
    }
  }, [activeSlug, categories]);

  const triggerClassName = linkClassName ?? defaultTriggerClassName;

  const flagOrIcon = (
    <>
      {showCountryFlag ? <CountryFlag country="CN" size={18} decorative /> : null}
      {showHamburger && !showCountryFlag ? (
        <span className="text-base leading-none text-zinc-700">☰</span>
      ) : null}
      {showGridIcon && !showCountryFlag ? (
        <GridIcon className="h-4 w-4 text-zinc-500" />
      ) : null}
    </>
  );

  if (isLoading && !menu) {
    return (
      <div className="group relative">
        <button type="button" className={triggerClassName} aria-busy="true" aria-label={triggerLabel}>
          {flagOrIcon}
          <span>{triggerLabel}</span>
          <ChevronDownIcon className="h-3.5 w-3.5 opacity-60" />
        </button>
      </div>
    );
  }

  if (!activeCategory || categories.length === 0) {
    return (
      <Link href="/products?origin=china" className={triggerClassName} aria-label={triggerLabel}>
        {flagOrIcon}
        <span>{triggerLabel}</span>
      </Link>
    );
  }

  if (mobile) {
    return (
      <div className="max-h-[min(70vh,32rem)] overflow-y-auto [scrollbar-width:thin]">
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          className="flex min-h-11 w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-left text-[15px] font-medium leading-tight text-zinc-900 transition-all duration-200 ease-out active:bg-zinc-50"
          aria-expanded={open}
          aria-controls={menuId}
        >
          <span className="flex min-w-0 items-center gap-3">
            {showCountryFlag ? <CountryFlag country="CN" size={18} decorative /> : null}
            {showHamburger && !showCountryFlag ? (
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-base leading-none text-zinc-600">
                ☰
              </span>
            ) : null}
            <span className="truncate">{triggerLabel}</span>
          </span>
          <ChevronDownIcon
            className={`h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 ease-out ${
              open ? "rotate-180" : ""
            }`}
          />
        </button>
        {open ? (
          <div id={menuId} className="pb-1.5 pt-1">
            <p className="px-3 pb-2 text-[12px] text-zinc-500">
              Import products directly from China.
            </p>
            <ul className="space-y-0.5">
              {categories.map((category) => {
                const isExpanded = expandedSlug === category.slug;
                const children = category.children ?? [];
                const icon = enrichApiCategoryFromStatic({
                  slug: category.slug,
                  name: category.name,
                }).icon;

                return (
                  <li key={category.id}>
                    <button
                      type="button"
                      onClick={() => {
                        setExpandedSlug((current) => (current === category.slug ? null : category.slug));
                        setActiveSlug(category.slug);
                      }}
                      className="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-left transition-all duration-200 ease-out active:bg-zinc-50"
                      aria-expanded={isExpanded}
                    >
                      <span className="flex min-w-0 items-center gap-3">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-50 text-base leading-none ring-1 ring-zinc-100">
                          {icon}
                        </span>
                        <span className="truncate text-[14px] font-medium text-zinc-800">
                          {category.name}
                        </span>
                      </span>
                      <ChevronDownIcon
                        className={`h-3.5 w-3.5 shrink-0 text-zinc-400 transition-transform duration-200 ease-out ${
                          isExpanded ? "rotate-180" : ""
                        }`}
                      />
                    </button>
                    {isExpanded ? (
                      <ul className="mb-1.5 ml-[22px] max-h-52 space-y-0.5 overflow-y-auto border-l-2 border-zinc-100 pl-4 [scrollbar-width:thin]">
                        {children.map((sub: ApiCatalogCategory) => (
                          <li key={sub.id}>
                            <Link
                              href={categoryHref(sub.slug)}
                              onClick={onNavigate}
                              className="block rounded-lg px-2 py-1.5 text-[13px] text-zinc-500 transition-colors duration-200 ease-out active:bg-zinc-50 active:text-[#c9a227]"
                            >
                              {sub.name}
                            </Link>
                          </li>
                        ))}
                        <li>
                          <Link
                            href={categoryHref(category.slug)}
                            onClick={onNavigate}
                            className="block rounded-lg px-2 py-1.5 text-[13px] font-semibold text-[#c9a227]"
                          >
                            Shop all {category.name}
                          </Link>
                        </li>
                      </ul>
                    ) : null}
                  </li>
                );
              })}
            </ul>
            <Link
              href="/products?origin=china"
              onClick={onNavigate}
              className="mt-2 flex items-center gap-1.5 rounded-lg px-3 py-2 text-[13px] font-semibold text-[#c9a227]"
            >
              View all China imports
              <ArrowRightIcon className="h-3 w-3" />
            </Link>
          </div>
        ) : null}
      </div>
    );
  }

  const brands = menu?.brands ?? [];
  const featured = menu?.featured_products ?? [];
  const subcategories = activeCategory.children ?? [];

  return (
    <div
      ref={rootRef}
      className="group relative"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button
        type="button"
        className={`${triggerClassName} ${
          open ? "bg-zinc-50/90 text-zinc-900" : ""
        }`}
        aria-expanded={open}
        aria-haspopup="true"
        aria-controls={menuId}
        aria-label={triggerLabel}
        onClick={() => setOpen((value) => !value)}
      >
        {showCountryFlag ? <CountryFlag country="CN" size={18} decorative /> : null}
        {showHamburger && !showCountryFlag ? (
          <span className="text-base leading-none text-zinc-700">☰</span>
        ) : null}
        {showGridIcon && !showCountryFlag ? (
          <GridIcon
            className={`h-4 w-4 shrink-0 transition-colors duration-200 ease-out ${
              open ? "text-[#c9a227]" : "text-zinc-500 group-hover:text-zinc-700"
            }`}
          />
        ) : null}
        <span>{triggerLabel}</span>
        <ChevronDownIcon
          className={`h-3.5 w-3.5 shrink-0 transition-all duration-200 ease-out ${
            open ? "rotate-180 text-[#c9a227]" : "text-zinc-400 opacity-70 group-hover:text-zinc-600"
          }`}
        />
      </button>

      <div
        id={menuId}
        role="region"
        aria-label={`${triggerLabel} menu`}
        className={`absolute top-full z-50 w-[min(860px,calc(100vw-2rem))] pt-2.5 transition-all duration-300 ease-out motion-reduce:transition-none ${
          align === "left" ? "left-0" : "left-1/2 -translate-x-1/2"
        } ${
          open
            ? "pointer-events-auto visible translate-y-0 opacity-100"
            : "pointer-events-none invisible -translate-y-1.5 opacity-0"
        }`}
      >
        <div className="max-h-[calc(100vh-5.5rem)] overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-[0_20px_50px_-12px_rgba(0,0,0,0.18)] ring-1 ring-zinc-900/[0.04]">
          <div className="grid max-h-[calc(100vh-5.5rem)] grid-cols-[220px_1fr] overflow-hidden">
            <div className="overflow-y-auto border-r border-zinc-100 bg-gradient-to-b from-zinc-50/90 to-zinc-50/50 p-2.5 [scrollbar-width:thin]">
              <p className="px-3 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400">
                Categories
              </p>
              {categories.map((category) => {
                const icon = enrichApiCategoryFromStatic({
                  slug: category.slug,
                  name: category.name,
                }).icon;
                return (
                  <button
                    key={category.id}
                    type="button"
                    onMouseEnter={() => setActiveSlug(category.slug)}
                    className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[13px] transition-all duration-200 ease-out ${
                      resolvedActiveSlug === category.slug
                        ? "border-l-2 border-[#c9a227] bg-white pl-[10px] font-semibold text-zinc-900 shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-zinc-200/60"
                        : "border-l-2 border-transparent text-zinc-600 hover:bg-white/70 hover:text-zinc-900"
                    }`}
                  >
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-base leading-none shadow-sm ring-1 ring-zinc-100">
                      {icon}
                    </span>
                    <span className="truncate leading-snug">{category.name}</span>
                  </button>
                );
              })}
              <Link
                href="/products?origin=china"
                className="mt-2.5 flex items-center gap-1.5 rounded-lg px-3 py-2 text-[11px] font-bold uppercase tracking-[0.08em] text-[#c9a227] transition-colors duration-200 ease-out hover:bg-white/60 hover:text-[#8b6914]"
              >
                View all
                <ArrowRightIcon className="h-3 w-3" />
              </Link>
            </div>

            <div className="flex min-h-0 flex-col overflow-y-auto p-6 [scrollbar-width:thin]">
              <div className="sticky top-0 z-10 -mx-6 border-b border-zinc-100 bg-white px-6 pb-4">
                <div className="flex items-start justify-between gap-5">
                  <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#c9a227]">
                      {STOREFRONT_NAV_LABELS.orderFromChina}
                    </p>
                    <h3 className="mt-1 text-[1.125rem] font-bold tracking-tight text-zinc-900">
                      {activeCategory.name}
                    </h3>
                    <p className="mt-1.5 max-w-md text-[13px] leading-relaxed text-zinc-500">
                      {presentation?.description ||
                        "Import products directly from China."}
                    </p>
                  </div>
                  <Link
                    href={categoryHref(activeCategory.slug)}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-zinc-900 px-4 py-2.5 text-[11px] font-bold uppercase tracking-[0.06em] text-white shadow-sm transition-all duration-200 ease-out hover:bg-[#c9a227] hover:text-zinc-900 hover:shadow-md"
                  >
                    Shop now
                    <ArrowRightIcon className="h-3 w-3" />
                  </Link>
                </div>
              </div>

              {subcategories.length > 0 ? (
                <div className="mt-5">
                  <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                    Subcategories
                  </p>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-0.5 xl:grid-cols-3">
                    {subcategories.map((sub) => (
                      <Link
                        key={sub.id}
                        href={categoryHref(sub.slug)}
                        className="group/sub flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-zinc-600 transition-all duration-200 ease-out hover:bg-zinc-50 hover:text-[#c9a227]"
                      >
                        <span className="h-1 w-1 shrink-0 rounded-full bg-zinc-300 transition-colors duration-200 ease-out group-hover/sub:bg-[#c9a227]" />
                        <span className="truncate">{sub.name}</span>
                      </Link>
                    ))}
                  </div>
                </div>
              ) : null}

              {brands.length > 0 ? (
                <div className="mt-6">
                  <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                    Brands
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {brands.map((brand) => (
                      <Link
                        key={brand.id}
                        href={brandHref(brand.slug, activeCategory.slug)}
                        className="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-[12px] font-medium text-zinc-700 transition hover:border-[#c9a227]/40 hover:text-[#c9a227]"
                      >
                        {brand.name}
                      </Link>
                    ))}
                  </div>
                </div>
              ) : null}

              <div className="mt-6">
                <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Featured products
                </p>
                {featured.length === 0 ? (
                  <p className="text-[13px] text-zinc-500">
                    No published China-import products in this category yet.
                  </p>
                ) : (
                  <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {featured.slice(0, 6).map((product) => {
                      const image = productImage(product);
                      return (
                        <Link
                          key={product.id}
                          href={`/products/${product.slug}`}
                          className="group/card overflow-hidden rounded-xl border border-zinc-100 bg-zinc-50/80 transition hover:border-[#c9a227]/35 hover:shadow-sm"
                        >
                          <div className="relative aspect-square bg-zinc-100">
                            {image ? (
                              <Image
                                src={image}
                                alt={product.name}
                                fill
                                className="object-cover transition duration-300 group-hover/card:scale-105"
                                unoptimized
                              />
                            ) : (
                              <span className="flex h-full items-center justify-center text-2xl">
                                {presentation?.icon || "📦"}
                              </span>
                            )}
                          </div>
                          <div className="p-2.5">
                            <p className="truncate text-[12px] font-medium text-zinc-800">
                              {product.name}
                            </p>
                            {product.brand?.name ? (
                              <p className="mt-0.5 truncate text-[10px] uppercase tracking-wide text-zinc-400">
                                {product.brand.name}
                              </p>
                            ) : null}
                          </div>
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
