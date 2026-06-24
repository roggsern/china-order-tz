"use client";

import { useState } from "react";
import { ArrowRightIcon } from "./icons";

export function Newsletter() {
  const [email, setEmail] = useState("");
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (email.trim()) {
      setSubmitted(true);
      setEmail("");
    }
  }

  return (
    <section className="relative overflow-hidden bg-zinc-950 py-20 sm:py-24">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute left-1/2 top-0 h-64 w-64 -translate-x-1/2 rounded-full bg-[#c9a227]/15 blur-3xl" />
      </div>

      <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
          Stay in the Loop
        </p>
        <h2 className="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">
          Get Exclusive Deals &amp; Updates
        </h2>
        <p className="mt-3 text-base text-zinc-400">
          Subscribe to our newsletter for flash sales, new arrivals, and import tips delivered
          straight to your inbox.
        </p>

        {submitted ? (
          <div className="mt-10 rounded-2xl border border-[#c9a227]/30 bg-[#c9a227]/10 px-6 py-8">
            <p className="text-lg font-semibold text-[#e8c547]">You&apos;re subscribed!</p>
            <p className="mt-2 text-sm text-zinc-400">
              Thank you for joining CHINA ORDER TZ. Check your inbox for a welcome offer.
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="mt-10 flex flex-col gap-3 sm:flex-row sm:gap-0">
            <label className="sr-only" htmlFor="newsletter-email">
              Email address
            </label>
            <input
              id="newsletter-email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Enter your email address"
              className="flex-1 rounded-full border border-zinc-700 bg-zinc-900 px-6 py-3.5 text-sm text-white outline-none placeholder:text-zinc-500 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20 sm:rounded-r-none"
            />
            <button
              type="submit"
              className="inline-flex items-center justify-center gap-2 rounded-full bg-[#c9a227] px-8 py-3.5 text-sm font-bold uppercase tracking-wide text-zinc-900 transition hover:bg-[#e8c547] sm:rounded-l-none"
            >
              Subscribe
              <ArrowRightIcon className="h-4 w-4" />
            </button>
          </form>
        )}

        <p className="mt-4 text-xs text-zinc-600">
          No spam. Unsubscribe anytime. We respect your privacy.
        </p>
      </div>
    </section>
  );
}
