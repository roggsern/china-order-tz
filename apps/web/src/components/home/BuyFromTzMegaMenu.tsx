"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { CountryFlag } from "@/components/storefront/CountryFlag";
import { useTzStores } from "@/lib/catalog/use-tz-stores";
import { getTzStoreCategories } from "@/lib/api/tz-stores";
import type { ApiCatalogCategory } from "@/lib/api/products";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";
import { ArrowRightIcon, ChevronDownIcon, StoreIcon } from "./icons";

interface BuyFromTzMegaMenuProps {
  mobile?: boolean;
  onNavigate?: () => void;
  linkClassName?: string;
  triggerLabel?: string;
  showCountryFlag?: boolean;
  align?: "left" | "center";
}

const defaultTriggerClassName =
  "inline-flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-[13px] font-medium text-zinc-600 transition-all duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900";

function StoreLogo({
  name,
  logoUrl,
  themeColor,
  size = 32,
}: {
  name: string;
  logoUrl?: string | null;
  themeColor?: string | null;
  size?: number;
}) {
  if (logoUrl) {
    return (
      <Image
        src={logoUrl}
        alt={`${name} logo`}
        width={size}
        height={size}
        className="h-full w-full rounded-lg object-cover"
        unoptimized
      />
    );
  }

  return (
    <span
      className="flex h-full w-full items-center justify-center rounded-lg text-sm font-bold text-white"
      style={{ backgroundColor: themeColor || "#c9a227" }}
      aria-hidden
    >
      {name.trim().charAt(0) || "S"}
    </span>
  );
}

/**
 * Buy from TZ mega menu — storefront stores only (never catalog brands).
 */
export function BuyFromTzMegaMenu({
  mobile = false,
  onNavigate,
  linkClassName,
  triggerLabel = STOREFRONT_NAV_LABELS.buyFromTz,
  showCountryFlag = false,
  align = "left",
}: BuyFromTzMegaMenuProps) {
  const { stores, isLoading } = useTzStores();
  const [activeSlug, setActiveSlug] = useState("");
  const [open, setOpen] = useState(false);
  const [expandedSlug, setExpandedSlug] = useState<string | null>(null);
  const [categoriesByStore, setCategoriesByStore] = useState<Record<string, ApiCatalogCategory[]>>({});
  const rootRef = useRef<HTMLDivElement>(null);
  const menuId = useId();

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

  const resolvedActiveSlug = activeSlug || stores[0]?.slug || "";
  const activeStore = useMemo(
    () => stores.find((store) => store.slug === resolvedActiveSlug) ?? stores[0],
    [stores, resolvedActiveSlug],
  );

  useEffect(() => {
    if (!resolvedActiveSlug) return;
    let cancelled = false;

    void getTzStoreCategories(resolvedActiveSlug)
      .then((categories) => {
        if (cancelled) return;
        setCategoriesByStore((current) =>
          current[resolvedActiveSlug] ? current : { ...current, [resolvedActiveSlug]: categories },
        );
      })
      .catch(() => {
        if (cancelled) return;
        setCategoriesByStore((current) =>
          current[resolvedActiveSlug] ? current : { ...current, [resolvedActiveSlug]: [] },
        );
      });

    return () => {
      cancelled = true;
    };
  }, [resolvedActiveSlug]);

  const activeCategories = categoriesByStore[resolvedActiveSlug] ?? [];

  const triggerIcon = showCountryFlag ? (
    <CountryFlag country="TZ" size={18} decorative />
  ) : (
    <StoreIcon className="h-4 w-4 shrink-0 text-zinc-500" />
  );

  if (isLoading) {
    return (
      <div className="group relative">
        <button
          type="button"
          className={linkClassName ?? defaultTriggerClassName}
          aria-busy="true"
          aria-label={triggerLabel}
        >
          {triggerIcon}
          <span>{triggerLabel}</span>
          <ChevronDownIcon className="h-3.5 w-3.5 opacity-60" />
        </button>
      </div>
    );
  }

  if (!activeStore || stores.length === 0) {
    return (
      <Link
        href="/buy-from-tz"
        className={linkClassName ?? defaultTriggerClassName}
        aria-label={triggerLabel}
      >
        {triggerIcon}
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
            {showCountryFlag ? <CountryFlag country="TZ" size={18} decorative /> : null}
            <span className="truncate">{triggerLabel}</span>
          </span>
          <ChevronDownIcon
            className={`h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 ease-out ${
              open ? "rotate-180" : ""
            }`}
          />
        </button>
        {open ? (
          <div id={menuId}>
            <p className="px-3 pb-2 text-[12px] text-zinc-500">
              Shop from trusted Tanzanian stores.
            </p>
          <ul className="space-y-0.5 pb-1.5 pt-1">
            {stores.map((store) => {
              const isExpanded = expandedSlug === store.slug;
              const categories = categoriesByStore[store.slug] ?? [];

              return (
                <li key={store.id}>
                  <button
                    type="button"
                    onClick={() => {
                      setExpandedSlug((current) => (current === store.slug ? null : store.slug));
                      setActiveSlug(store.slug);
                    }}
                    className="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-left transition-all duration-200 ease-out active:bg-zinc-50"
                    aria-expanded={isExpanded}
                  >
                    <span className="flex min-w-0 items-center gap-3">
                      <span className="relative h-8 w-8 shrink-0 overflow-hidden rounded-lg ring-1 ring-[#c9a227]/20">
                        <StoreLogo
                          name={store.name}
                          logoUrl={store.logo_url}
                          themeColor={store.theme_color}
                        />
                      </span>
                      <span className="truncate text-[14px] font-semibold text-[#c9a227]">
                        {store.name}
                      </span>
                    </span>
                    <ChevronDownIcon
                      className={`h-3.5 w-3.5 shrink-0 text-zinc-400 transition-transform duration-200 ease-out ${
                        isExpanded ? "rotate-180" : ""
                      }`}
                    />
                  </button>
                  {isExpanded ? (
                    <ul className="mb-1.5 ml-[22px] max-h-52 space-y-0.5 overflow-y-auto border-l-2 border-[#c9a227]/20 pl-4 [scrollbar-width:thin]">
                      {categories.map((category) => (
                        <li key={category.id}>
                          <Link
                            href={`/buy-from-tz/${store.slug}/category/${category.slug}`}
                            onClick={onNavigate}
                            className="block rounded-lg px-2 py-1.5 text-[13px] text-zinc-500 transition-colors duration-200 ease-out active:bg-zinc-50 active:text-[#c9a227]"
                          >
                            {category.name}
                          </Link>
                        </li>
                      ))}
                      <li>
                        <Link
                          href={`/buy-from-tz/${store.slug}`}
                          onClick={onNavigate}
                          className="block rounded-lg px-2 py-1.5 text-[13px] font-semibold text-[#c9a227]"
                        >
                          Shop {store.name}
                        </Link>
                      </li>
                    </ul>
                  ) : null}
                </li>
              );
            })}
          </ul>
          </div>
        ) : null}
      </div>
    );
  }

  return (
    <div
      ref={rootRef}
      className="group relative"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button
        type="button"
        className={`${linkClassName ?? defaultTriggerClassName} ${
          open ? "bg-zinc-50 text-zinc-900" : ""
        }`}
        aria-expanded={open}
        aria-haspopup="true"
        aria-controls={menuId}
        aria-label={triggerLabel}
        onClick={() => setOpen((value) => !value)}
      >
        {showCountryFlag ? (
          <CountryFlag country="TZ" size={18} decorative />
        ) : (
          <StoreIcon
            className={`h-4 w-4 shrink-0 transition-colors duration-200 ease-out ${
              open ? "text-[#c9a227]" : "text-zinc-500 group-hover:text-zinc-700"
            }`}
          />
        )}
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
        className={`absolute top-full z-50 w-[min(820px,calc(100vw-2rem))] pt-2.5 transition-all duration-300 ease-out motion-reduce:transition-none ${
          align === "left" ? "left-0" : "left-1/2 -translate-x-1/2"
        } ${
          open
            ? "pointer-events-auto visible translate-y-0 opacity-100"
            : "pointer-events-none invisible -translate-y-1.5 opacity-0"
        }`}
      >
        <div
          className="max-h-[calc(100vh-5.5rem)] overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-[0_20px_50px_-12px_rgba(0,0,0,0.18)] ring-1 ring-zinc-900/[0.04]"
        >
          <div className="grid max-h-[calc(100vh-5.5rem)] grid-cols-[248px_1fr] overflow-hidden">
            <div className="sticky top-0 overflow-y-auto border-r border-zinc-100 bg-gradient-to-b from-[#c9a227]/[0.06] to-zinc-50/50 p-2.5 [scrollbar-width:thin]">
              {stores.map((store) => (
                <button
                  key={store.id}
                  type="button"
                  onMouseEnter={() => setActiveSlug(store.slug)}
                  className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[12px] transition-all duration-200 ease-out ${
                    resolvedActiveSlug === store.slug
                      ? "border-l-2 border-[#c9a227] bg-white pl-[10px] font-semibold text-zinc-900 shadow-[0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-zinc-200/60"
                      : "border-l-2 border-transparent text-zinc-600 hover:bg-white/70 hover:text-zinc-900"
                  }`}
                >
                  <span className="relative h-8 w-8 shrink-0 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-zinc-100">
                    <StoreLogo
                      name={store.name}
                      logoUrl={store.logo_url}
                      themeColor={store.theme_color}
                    />
                  </span>
                  <span className="truncate leading-snug">{store.name}</span>
                </button>
              ))}
              <Link
                href="/buy-from-tz"
                className="mt-2.5 flex items-center gap-1.5 rounded-lg px-3 py-2 text-[11px] font-bold uppercase tracking-[0.08em] text-[#c9a227] transition-colors duration-200 ease-out hover:bg-white/60 hover:text-[#8b6914]"
              >
                View all stores
                <ArrowRightIcon className="h-3 w-3" />
              </Link>
            </div>

            <div className="flex min-h-0 flex-col overflow-y-auto p-6 [scrollbar-width:thin]">
              <div className="sticky top-0 z-10 -mx-6 border-b border-zinc-100 bg-white px-6 pb-4">
                <div className="flex items-start justify-between gap-5">
                  <div className="flex min-w-0 items-start gap-3">
                    <span className="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-1 ring-zinc-200">
                      <StoreLogo
                        name={activeStore.name}
                        logoUrl={activeStore.logo_url}
                        themeColor={activeStore.theme_color}
                        size={48}
                      />
                    </span>
                    <div className="min-w-0">
                      <h3 className="text-[1.125rem] font-bold tracking-tight text-[#c9a227]">
                        {activeStore.name}
                      </h3>
                      <p className="mt-1 max-w-md text-[13px] leading-relaxed text-zinc-500">
                        {activeStore.description || "Shop from trusted Tanzanian stores."}
                      </p>
                    </div>
                  </div>
                  <Link
                    href={`/buy-from-tz/${activeStore.slug}`}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[#c9a227] px-4 py-2.5 text-[11px] font-bold uppercase tracking-[0.06em] text-zinc-900 shadow-sm transition-all duration-200 ease-out hover:bg-[#e8c547] hover:shadow-md"
                  >
                    Shop store
                    <ArrowRightIcon className="h-3 w-3" />
                  </Link>
                </div>
              </div>

              <div className="mt-5 min-h-0 flex-1">
                {activeCategories.length === 0 ? (
                  <p className="text-[13px] text-zinc-500">
                    Categories for this store will appear once they are published.
                  </p>
                ) : (
                  <div className="grid grid-cols-2 gap-x-6 gap-y-0.5 xl:grid-cols-3">
                    {activeCategories.map((category) => (
                      <Link
                        key={category.id}
                        href={`/buy-from-tz/${activeStore.slug}/category/${category.slug}`}
                        className="group/sub flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-zinc-600 transition-all duration-200 ease-out hover:bg-zinc-50 hover:text-[#c9a227]"
                      >
                        <span className="h-1 w-1 shrink-0 rounded-full bg-zinc-300 transition-colors duration-200 ease-out group-hover/sub:bg-[#c9a227]" />
                        <span className="truncate">{category.name}</span>
                      </Link>
                    ))}
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

/** @deprecated Use BuyFromTzMegaMenu — kept for import compatibility */
export { BuyFromTzMegaMenu as BrandMegaMenu };
