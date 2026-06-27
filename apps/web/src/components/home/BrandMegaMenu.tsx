"use client";

import { useState } from "react";
import Link from "next/link";
import {
  buyFromTzBrandMenu,
  getBrandCategoryHref,
} from "@/lib/catalog/brands";
import { ArrowRightIcon, ChevronDownIcon } from "./icons";

interface BrandMegaMenuProps {
  mobile?: boolean;
  onNavigate?: () => void;
  linkClassName?: string;
  triggerLabel?: string;
  align?: "left" | "center";
}

export function BrandMegaMenu({
  mobile = false,
  onNavigate,
  linkClassName,
  triggerLabel = "Buy From TZ",
  align = "left",
}: BrandMegaMenuProps) {
  const [activeSlug, setActiveSlug] = useState(buyFromTzBrandMenu[0].slug);
  const [open, setOpen] = useState(false);
  const [expandedBrandSlug, setExpandedBrandSlug] = useState<string | null>(null);

  const activeBrand =
    buyFromTzBrandMenu.find((brand) => brand.slug === activeSlug) ??
    buyFromTzBrandMenu[0];

  if (mobile) {
    const toggleBrand = (slug: string) => {
      setExpandedBrandSlug((current) => (current === slug ? null : slug));
    };

    return (
      <div>
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-[15px] font-semibold leading-tight text-zinc-900 transition-colors active:bg-zinc-100"
          aria-expanded={open}
        >
          <span className="truncate">{triggerLabel}</span>
          <ChevronDownIcon
            className={`h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 ${
              open ? "rotate-180" : ""
            }`}
          />
        </button>
        {open && (
          <div className="pb-1 pt-0.5">
            <ul className="space-y-0.5">
              {buyFromTzBrandMenu.map((brand) => {
                const isExpanded = expandedBrandSlug === brand.slug;

                return (
                  <li key={brand.slug}>
                    <button
                      type="button"
                      onClick={() => toggleBrand(brand.slug)}
                      className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left transition-colors active:bg-zinc-50"
                      aria-expanded={isExpanded}
                    >
                      <span className="flex min-w-0 items-center gap-3">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center text-base leading-none">
                          {brand.icon}
                        </span>
                        <span className="truncate text-[14px] font-medium text-[#c9a227]">
                          {brand.name}
                        </span>
                      </span>
                      <ChevronDownIcon
                        className={`h-3.5 w-3.5 shrink-0 text-zinc-400 transition-transform duration-200 ${
                          isExpanded ? "rotate-180" : ""
                        }`}
                      />
                    </button>
                    {isExpanded && (
                      <ul className="mb-1 ml-9 space-y-0.5 border-l border-zinc-100 pl-3">
                        {brand.subcategories.map((sub) => (
                          <li key={sub.slug}>
                            <Link
                              href={getBrandCategoryHref(brand.slug, sub.slug)}
                              onClick={onNavigate}
                              className="block rounded-md py-1.5 text-[13px] text-zinc-500 transition-colors active:text-[#c9a227]"
                            >
                              {sub.name}
                            </Link>
                          </li>
                        ))}
                        <li>
                          <Link
                            href={`/products?brand=${brand.slug}`}
                            onClick={onNavigate}
                            className="block py-1.5 text-[13px] font-semibold text-[#c9a227]"
                          >
                            Shop all {brand.name}
                          </Link>
                        </li>
                      </ul>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>
        )}
      </div>
    );
  }

  return (
    <div
      className="group relative"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button
        type="button"
        className={
          linkClassName ??
          "inline-flex items-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 hover:text-zinc-900"
        }
        aria-expanded={open}
        aria-haspopup="true"
      >
        {triggerLabel}
        <ChevronDownIcon className="h-3.5 w-3.5 opacity-60 transition group-hover:rotate-180" />
      </button>

      <div
        className={`absolute top-full z-50 w-[720px] pt-2 transition-all duration-200 ${
          align === "left" ? "left-0" : "left-1/2 -translate-x-1/2"
        } ${
          open
            ? "pointer-events-auto visible translate-y-0 opacity-100"
            : "pointer-events-none invisible -translate-y-2 opacity-0"
        }`}
      >
        <div className="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-2xl shadow-zinc-900/10 ring-1 ring-zinc-900/5">
          <div className="grid grid-cols-[220px_1fr]">
            <div className="border-r border-zinc-100 bg-zinc-50/80 p-2">
              {buyFromTzBrandMenu.map((brand) => (
                <button
                  key={brand.slug}
                  type="button"
                  onMouseEnter={() => setActiveSlug(brand.slug)}
                  className={`flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm transition ${
                    activeSlug === brand.slug
                      ? "bg-white font-semibold text-zinc-900 shadow-sm ring-1 ring-zinc-200/80"
                      : "text-zinc-600 hover:bg-white/60 hover:text-zinc-900"
                  }`}
                >
                  <span className="text-base">{brand.icon}</span>
                  <span className="truncate">{brand.name}</span>
                </button>
              ))}
              <Link
                href={`/products?brand=${activeBrand.slug}`}
                className="mt-2 flex items-center gap-1 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[#c9a227] hover:text-[#8b6914]"
              >
                View all
                <ArrowRightIcon className="h-3 w-3" />
              </Link>
            </div>

            <div className="p-6">
              <h3 className="text-lg font-bold text-[#c9a227]">{activeBrand.name}</h3>

              <div className="mt-5 grid grid-cols-2 gap-x-6 gap-y-2">
                {activeBrand.subcategories.map((sub) => (
                  <Link
                    key={sub.slug}
                    href={getBrandCategoryHref(activeBrand.slug, sub.slug)}
                    className="group/sub flex items-center gap-2 rounded-lg py-1.5 text-sm text-zinc-600 transition hover:text-[#c9a227]"
                  >
                    <span className="h-1 w-1 rounded-full bg-zinc-300 transition group-hover/sub:bg-[#c9a227]" />
                    {sub.name}
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
