"use client";

import { useState } from "react";
import Link from "next/link";
import { chinaOrderActions, supportedPlatforms } from "@/lib/home-data";
import { ArrowRightIcon, DocumentIcon, LinkIcon, UploadIcon } from "./icons";

const actionIconMap = {
  upload: UploadIcon,
  link: LinkIcon,
  quote: DocumentIcon,
} as const;

export function OrderFromChina() {
  const [activeTab, setActiveTab] = useState<(typeof chinaOrderActions)[number]["id"]>("link");
  const [productLink, setProductLink] = useState("");

  const activeAction = chinaOrderActions.find((a) => a.id === activeTab) ?? chinaOrderActions[1];

  return (
    <section id="order-from-china" className="relative overflow-hidden bg-zinc-950 py-20 sm:py-28">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -left-40 top-1/4 h-[500px] w-[500px] rounded-full bg-[#c9a227]/10 blur-3xl" />
        <div className="absolute -right-40 bottom-0 h-[400px] w-[400px] rounded-full bg-red-600/5 blur-3xl" />
        <div
          className="absolute inset-0 opacity-[0.03]"
          style={{
            backgroundImage:
              "linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px)",
            backgroundSize: "48px 48px",
          }}
        />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.25em] text-[#e8c547]">
            China Order Service
          </p>
          <h2 className="mt-3 text-4xl font-bold uppercase tracking-tight text-white sm:text-5xl lg:text-6xl">
            Order From{" "}
            <span className="bg-gradient-to-r from-[#e8c547] via-[#c9a227] to-[#f5d76e] bg-clip-text text-transparent">
              China
            </span>
          </h2>
          <p className="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-zinc-400 sm:text-lg">
            Can&apos;t find it in our catalog? Submit a link or image from any Chinese marketplace
            and we&apos;ll handle sourcing, quotation, and delivery to Tanzania.
          </p>
        </div>

        <div className="mt-14 grid gap-5 sm:grid-cols-3">
          {chinaOrderActions.map((action) => {
            const Icon = actionIconMap[action.icon];
            const isActive = activeTab === action.id;
            return (
              <button
                key={action.id}
                type="button"
                onClick={() => setActiveTab(action.id)}
                className={`group relative rounded-2xl border p-6 text-left transition-all duration-300 ${
                  isActive
                    ? "border-[#c9a227]/50 bg-[#c9a227]/10 shadow-lg shadow-[#c9a227]/10"
                    : "border-zinc-800 bg-zinc-900/50 hover:border-zinc-700 hover:bg-zinc-900/80"
                }`}
              >
                <div
                  className={`flex h-12 w-12 items-center justify-center rounded-xl transition ${
                    isActive
                      ? "bg-[#c9a227] text-zinc-900"
                      : "bg-zinc-800 text-[#c9a227] group-hover:bg-zinc-700"
                  }`}
                >
                  <Icon className="h-6 w-6" />
                </div>
                <h3 className="mt-5 text-lg font-bold text-white">{action.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-zinc-400">{action.description}</p>
                {isActive && (
                  <span className="absolute right-4 top-4 h-2 w-2 rounded-full bg-[#c9a227]" />
                )}
              </button>
            );
          })}
        </div>

        <div className="mx-auto mt-10 max-w-3xl">
          <div className="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 backdrop-blur sm:p-8">
            {activeTab === "upload" && (
              <div className="text-center">
                <div className="mx-auto flex h-32 w-full max-w-md cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-700 bg-zinc-800/50 transition hover:border-[#c9a227]/50 hover:bg-zinc-800">
                  <UploadIcon className="h-10 w-10 text-zinc-500" />
                  <p className="mt-3 text-sm font-medium text-zinc-300">
                    Drag & drop or click to upload
                  </p>
                  <p className="mt-1 text-xs text-zinc-500">JPEG, PNG, WebP — max 10 MB</p>
                </div>
                <button
                  type="button"
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-[#c9a227] px-8 py-3.5 text-sm font-bold uppercase tracking-wide text-zinc-900 transition hover:bg-[#e8c547]"
                >
                  {activeAction.cta}
                  <ArrowRightIcon className="h-4 w-4" />
                </button>
              </div>
            )}

            {activeTab === "link" && (
              <div>
                <label htmlFor="product-link" className="block text-sm font-medium text-zinc-300">
                  Product link from Alibaba, 1688, Taobao, or Temu
                </label>
                <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                  <input
                    id="product-link"
                    type="url"
                    value={productLink}
                    onChange={(e) => setProductLink(e.target.value)}
                    placeholder="https://www.alibaba.com/product-detail/..."
                    className="flex-1 rounded-xl border border-zinc-700 bg-zinc-800/80 px-4 py-3.5 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                  />
                  <button
                    type="button"
                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#c9a227] px-8 py-3.5 text-sm font-bold uppercase tracking-wide text-zinc-900 transition hover:bg-[#e8c547]"
                  >
                    {activeAction.cta}
                    <ArrowRightIcon className="h-4 w-4" />
                  </button>
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                  {supportedPlatforms.map((platform) => (
                    <span
                      key={platform}
                      className="rounded-full border border-zinc-700 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500"
                    >
                      {platform}
                    </span>
                  ))}
                </div>
              </div>
            )}

            {activeTab === "quote" && (
              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label htmlFor="quote-product" className="block text-sm font-medium text-zinc-300">
                      Product description
                    </label>
                    <input
                      id="quote-product"
                      type="text"
                      placeholder="e.g. Wireless earbuds, black, 500 units"
                      className="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-800/80 px-4 py-3 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                    />
                  </div>
                  <div>
                    <label htmlFor="quote-qty" className="block text-sm font-medium text-zinc-300">
                      Quantity
                    </label>
                    <input
                      id="quote-qty"
                      type="number"
                      min={1}
                      placeholder="100"
                      className="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-800/80 px-4 py-3 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                    />
                  </div>
                </div>
                <div>
                  <label htmlFor="quote-notes" className="block text-sm font-medium text-zinc-300">
                    Additional notes (optional)
                  </label>
                  <textarea
                    id="quote-notes"
                    rows={3}
                    placeholder="Size, color, shipping preference..."
                    className="mt-2 w-full resize-none rounded-xl border border-zinc-700 bg-zinc-800/80 px-4 py-3 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                  />
                </div>
                <button
                  type="button"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#c9a227] py-3.5 text-sm font-bold uppercase tracking-wide text-zinc-900 transition hover:bg-[#e8c547] sm:w-auto sm:px-10"
                >
                  {activeAction.cta}
                  <ArrowRightIcon className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>

          <p className="mt-6 text-center text-sm text-zinc-500">
            Free quotation within 24–48 hours · No commitment required ·{" "}
            <Link href="#how-it-works" className="font-medium text-[#c9a227] hover:text-[#e8c547]">
              Learn how it works
            </Link>
          </p>
        </div>
      </div>
    </section>
  );
}
