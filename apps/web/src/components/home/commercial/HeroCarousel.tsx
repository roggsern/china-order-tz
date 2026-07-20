"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { CountryFlag } from "@/components/storefront/CountryFlag";
import type { HomepageHeroSlide } from "@/lib/content/homepage";
import { ArrowRightIcon } from "../icons";

type HeroCarouselProps = {
  slides: HomepageHeroSlide[];
  autoPlayMs?: number;
};

function SlideAccent({ slide }: { slide: HomepageHeroSlide }) {
  if (slide.type === "china") {
    return <CountryFlag country="CN" size={22} decorative />;
  }
  if (slide.type === "tz") {
    return <CountryFlag country="TZ" size={22} decorative />;
  }
  if (slide.sponsorName) {
    return (
      <span className="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-white/10 px-2 text-[11px] font-bold uppercase tracking-wide text-[#e8c547] ring-1 ring-white/15">
        {slide.sponsorName}
      </span>
    );
  }
  return (
    <span className="inline-flex h-2 w-2 rounded-full bg-[#c9a227]" aria-hidden />
  );
}

export function HeroCarousel({ slides, autoPlayMs = 6500 }: HeroCarouselProps) {
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const touchStartX = useRef<number | null>(null);
  const regionRef = useRef<HTMLElement>(null);

  const count = slides.length;
  const safeIndex = count === 0 ? 0 : index % count;
  const active = slides[safeIndex];

  const goTo = useCallback(
    (next: number) => {
      if (count === 0) return;
      setIndex(((next % count) + count) % count);
    },
    [count],
  );

  const next = useCallback(() => goTo(safeIndex + 1), [goTo, safeIndex]);
  const prev = useCallback(() => goTo(safeIndex - 1), [goTo, safeIndex]);

  useEffect(() => {
    if (count <= 1 || paused) return;
    const prefersReduced =
      typeof window !== "undefined" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (prefersReduced) return;

    const id = window.setInterval(next, autoPlayMs);
    return () => window.clearInterval(id);
  }, [autoPlayMs, count, next, paused]);

  useEffect(() => {
    const node = regionRef.current;
    if (!node) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "ArrowRight") {
        event.preventDefault();
        next();
      }
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        prev();
      }
    };

    node.addEventListener("keydown", onKeyDown);
    return () => node.removeEventListener("keydown", onKeyDown);
  }, [next, prev]);

  if (!active || count === 0) {
    return null;
  }

  return (
    <section
      ref={regionRef}
      aria-roledescription="carousel"
      aria-label="Homepage promotions"
      tabIndex={0}
      className="relative overflow-hidden outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227] focus-visible:ring-offset-2"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onTouchStart={(event) => {
        touchStartX.current = event.changedTouches[0]?.clientX ?? null;
      }}
      onTouchEnd={(event) => {
        const start = touchStartX.current;
        const end = event.changedTouches[0]?.clientX;
        touchStartX.current = null;
        if (start == null || end == null) return;
        const delta = end - start;
        if (Math.abs(delta) < 40) return;
        if (delta < 0) next();
        else prev();
      }}
    >
      <div className="relative min-h-[420px] sm:min-h-[460px] lg:min-h-[520px]">
        {slides.map((slide, slideIndex) => {
          const isActive = slideIndex === safeIndex;
          return (
            <article
              key={slide.id}
              aria-hidden={!isActive}
              className={`absolute inset-0 transition-opacity duration-500 ease-out motion-reduce:transition-none ${
                isActive ? "pointer-events-auto opacity-100" : "pointer-events-none opacity-0"
              }`}
            >
              <div className={`absolute inset-0 ${slide.backgroundClass}`} aria-hidden />
              <div
                className="pointer-events-none absolute inset-0 opacity-30"
                aria-hidden
                style={{
                  backgroundImage:
                    "radial-gradient(circle at 20% 20%, rgba(201,162,39,0.25), transparent 40%), radial-gradient(circle at 80% 70%, rgba(255,255,255,0.06), transparent 35%)",
                }}
              />

              <div className="relative mx-auto flex min-h-[420px] max-w-7xl flex-col justify-center px-4 py-16 sm:min-h-[460px] sm:px-6 sm:py-20 lg:min-h-[520px] lg:px-8">
                <div className="max-w-xl">
                  <div className="mb-5 inline-flex items-center gap-2.5">
                    <SlideAccent slide={slide} />
                    {slide.subtitle ? (
                      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#e8c547]">
                        {slide.subtitle}
                      </p>
                    ) : null}
                  </div>

                  <h1 className="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-[3.25rem]">
                    {slide.title}
                  </h1>
                  <p className="mt-4 max-w-lg text-base leading-relaxed text-zinc-300 sm:text-lg">
                    {slide.description}
                  </p>

                  <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Link
                      href={slide.ctaHref}
                      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[#c9a227] px-7 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
                    >
                      {slide.ctaLabel}
                      <ArrowRightIcon className="h-4 w-4" />
                    </Link>
                    {slide.secondaryCtaLabel && slide.secondaryCtaHref ? (
                      <Link
                        href={slide.secondaryCtaHref}
                        className="inline-flex min-h-11 items-center justify-center rounded-full border border-white/20 bg-white/5 px-7 py-3 text-sm font-semibold text-white transition hover:border-[#c9a227]/50 hover:bg-white/10"
                      >
                        {slide.secondaryCtaLabel}
                      </Link>
                    ) : null}
                  </div>
                </div>
              </div>
            </article>
          );
        })}
      </div>

      {count > 1 ? (
        <>
          <div className="pointer-events-none absolute inset-x-0 bottom-6 z-10 flex justify-center gap-2">
            {slides.map((slide, slideIndex) => (
              <button
                key={slide.id}
                type="button"
                aria-label={`Go to slide ${slideIndex + 1}: ${slide.title}`}
                aria-current={slideIndex === safeIndex}
                onClick={() => goTo(slideIndex)}
                className={`pointer-events-auto h-2.5 rounded-full transition ${
                  slideIndex === safeIndex
                    ? "w-8 bg-[#c9a227]"
                    : "w-2.5 bg-white/35 hover:bg-white/60"
                }`}
              />
            ))}
          </div>

          <button
            type="button"
            aria-label="Previous slide"
            onClick={prev}
            className="absolute left-3 top-1/2 z-10 hidden min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-black/30 text-white backdrop-blur-sm transition hover:bg-black/50 sm:flex"
          >
            ‹
          </button>
          <button
            type="button"
            aria-label="Next slide"
            onClick={next}
            className="absolute right-3 top-1/2 z-10 hidden min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-black/30 text-white backdrop-blur-sm transition hover:bg-black/50 sm:flex"
          >
            ›
          </button>
        </>
      ) : null}
    </section>
  );
}
