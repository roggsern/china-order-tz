"use client";

import { useState } from "react";
import type { HomepageNewsletterCopy } from "@/lib/content/homepage";
import { ArrowRightIcon } from "../icons";

type CommercialNewsletterProps = {
  copy: HomepageNewsletterCopy;
};

export function CommercialNewsletter({ copy }: CommercialNewsletterProps) {
  const [email, setEmail] = useState("");
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!email.trim()) return;
    setSubmitted(true);
    setEmail("");
  }

  return (
    <section id="newsletter" className="relative overflow-hidden bg-zinc-950 py-16 sm:py-20">
      <div
        className="pointer-events-none absolute inset-0 opacity-40"
        aria-hidden
        style={{
          backgroundImage:
            "radial-gradient(circle at 50% 0%, rgba(201,162,39,0.22), transparent 45%)",
        }}
      />
      <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">{copy.title}</h2>
        <p className="mt-3 text-sm text-zinc-400 sm:text-base">{copy.description}</p>

        {submitted ? (
          <div className="mt-8 rounded-2xl border border-[#c9a227]/30 bg-[#c9a227]/10 px-6 py-6">
            <p className="text-lg font-semibold text-[#e8c547]">{copy.successTitle}</p>
            <p className="mt-2 text-sm text-zinc-400">{copy.successDescription}</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="mt-8 flex flex-col gap-3 sm:flex-row sm:gap-0">
            <label className="sr-only" htmlFor="commercial-newsletter-email">
              Email address
            </label>
            <input
              id="commercial-newsletter-email"
              type="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              placeholder={copy.placeholder}
              className="flex-1 rounded-full border border-zinc-700 bg-zinc-900 px-6 py-3.5 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20 sm:rounded-r-none"
            />
            <button
              type="submit"
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[#c9a227] px-8 py-3.5 text-sm font-bold text-zinc-900 transition hover:bg-[#e8c547] sm:rounded-l-none"
            >
              {copy.ctaLabel}
              <ArrowRightIcon className="h-4 w-4" />
            </button>
          </form>
        )}
      </div>
    </section>
  );
}
