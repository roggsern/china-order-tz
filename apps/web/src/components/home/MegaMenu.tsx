"use client";

import { useState } from "react";
import Link from "next/link";
import { megaMenuCategories } from "@/lib/home-data";
import { ArrowRightIcon, ChevronDownIcon } from "./icons";

interface MegaMenuProps {
  mobile?: boolean;
  onNavigate?: () => void;
  linkClassName?: string;
  triggerLabel?: string;
  showHamburger?: boolean;
  align?: "left" | "center";
}

export function MegaMenu({
  mobile = false,
  onNavigate,
  linkClassName,
  triggerLabel = "Categories",
  showHamburger = false,
  align = "center",
}: MegaMenuProps) {
  const [activeSlug, setActiveSlug] = useState(megaMenuCategories[0].slug);
  const [open, setOpen] = useState(false);
  const [expandedCategorySlug, setExpandedCategorySlug] = useState<string | null>(null);

  const activeCategory =
    megaMenuCategories.find((c) => c.slug === activeSlug) ?? megaMenuCategories[0];

  if (mobile) {
    const toggleCategory = (slug: string) => {
      setExpandedCategorySlug((current) => (current === slug ? null : slug));
    };

    return (
      <div>
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-[15px] font-semibold leading-tight text-zinc-900 transition-colors active:bg-zinc-100"
          aria-expanded={open}
        >
          <span className="flex min-w-0 items-center gap-3">
            {showHamburger && (
              <span className="flex h-[18px] w-[18px] shrink-0 items-center justify-center text-base leading-none text-zinc-600">
                ☰
              </span>
            )}
            {triggerLabel}
          </span>
          <ChevronDownIcon
            className={`h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 ${
              open ? "rotate-180" : ""
            }`}
          />
        </button>
        {open && (
          <div className="pb-1 pt-0.5">
            <ul className="space-y-0.5">
              {megaMenuCategories.map((category) => {
                const isExpanded = expandedCategorySlug === category.slug;

                return (
                  <li key={category.slug}>
                    <button
                      type="button"
                      onClick={() => toggleCategory(category.slug)}
                      className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left transition-colors active:bg-zinc-50"
                      aria-expanded={isExpanded}
                    >
                      <span className="flex min-w-0 items-center gap-3">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center text-base leading-none">
                          {category.icon}
                        </span>
                        <span className="truncate text-[14px] font-medium text-zinc-800">
                          {category.name}
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
                        {category.subcategories.map((sub) => (
                          <li key={sub}>
                            <Link
                              href={`/products?category=${category.slug}`}
                              onClick={onNavigate}
                              className="block rounded-md py-1.5 text-[13px] text-zinc-500 transition-colors active:text-[#c9a227]"
                            >
                              {sub}
                            </Link>
                          </li>
                        ))}
                        <li>
                          <Link
                            href={`/products?category=${category.slug}`}
                            onClick={onNavigate}
                            className="block py-1.5 text-[13px] font-semibold text-[#c9a227]"
                          >
                            Shop all {category.name}
                          </Link>
                        </li>
                      </ul>
                    )}
                  </li>
                );
              })}
            </ul>
            <Link
              href="/categories"
              onClick={onNavigate}
              className="mt-1 flex items-center gap-1.5 px-3 py-2 text-[13px] font-semibold text-[#c9a227]"
            >
              View all categories
              <ArrowRightIcon className="h-3 w-3" />
            </Link>
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
        {showHamburger && <span className="text-base leading-none text-zinc-700">☰</span>}
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
              {megaMenuCategories.map((category) => (
                <button
                  key={category.slug}
                  type="button"
                  onMouseEnter={() => setActiveSlug(category.slug)}
                  className={`flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm transition ${
                    activeSlug === category.slug
                      ? "bg-white font-semibold text-zinc-900 shadow-sm ring-1 ring-zinc-200/80"
                      : "text-zinc-600 hover:bg-white/60 hover:text-zinc-900"
                  }`}
                >
                  <span className="text-base">{category.icon}</span>
                  <span className="truncate">{category.name}</span>
                </button>
              ))}
              <Link
                href="/categories"
                className="mt-2 flex items-center gap-1 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[#c9a227] hover:text-[#8b6914]"
              >
                View all
                <ArrowRightIcon className="h-3 w-3" />
              </Link>
            </div>

            <div className="p-6">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-bold text-zinc-900">{activeCategory.name}</h3>
                  <p className="mt-1 text-sm text-zinc-500">{activeCategory.description}</p>
                </div>
                <Link
                  href={`/products?category=${activeCategory.slug}`}
                  className="inline-flex shrink-0 items-center gap-1 rounded-full bg-zinc-900 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
                >
                  Shop now
                  <ArrowRightIcon className="h-3 w-3" />
                </Link>
              </div>

              <div className="mt-5 grid grid-cols-2 gap-x-6 gap-y-2">
                {activeCategory.subcategories.map((sub) => (
                  <Link
                    key={sub}
                    href={`/products?category=${activeCategory.slug}`}
                    className="group/sub flex items-center gap-2 rounded-lg py-1.5 text-sm text-zinc-600 transition hover:text-[#c9a227]"
                  >
                    <span className="h-1 w-1 rounded-full bg-zinc-300 transition group-hover/sub:bg-[#c9a227]" />
                    {sub}
                  </Link>
                ))}
              </div>

              <div
                className={`mt-6 flex items-center gap-4 rounded-xl bg-gradient-to-r ${activeCategory.gradient} p-4`}
              >
                <span className="text-3xl">{activeCategory.icon}</span>
                <div className="min-w-0 flex-1">
                  <p className="text-xs font-semibold uppercase tracking-wider text-white/80">
                    Featured
                  </p>
                  <p className="truncate text-sm font-bold text-white">
                    {activeCategory.featured}
                  </p>
                </div>
                <Link
                  href={`/products?category=${activeCategory.slug}`}
                  className="shrink-0 rounded-full bg-white/20 px-3 py-1.5 text-xs font-bold text-white backdrop-blur transition hover:bg-white/30"
                >
                  Explore
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
