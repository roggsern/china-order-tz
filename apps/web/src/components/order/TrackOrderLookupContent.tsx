"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { motion } from "framer-motion";
import { paymentService } from "@/lib/payment/PaymentService";

const EXAMPLE_HINT = "e.g. CO-TZ-20250627-ABC123 or your order UUID";

export function TrackOrderLookupContent({ lookupPath = "/track" }: { lookupPath?: string }) {
  const router = useRouter();
  const [query, setQuery] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const trimmed = query.trim();
    if (!trimmed) {
      setError("Please enter your order ID or order number.");
      return;
    }

    setIsSubmitting(true);
    setError(null);

    const order = paymentService.resolveOrder(trimmed);
    if (!order) {
      setError("No order found. Check your order ID and try again.");
      setIsSubmitting(false);
      return;
    }

    router.push(`${lookupPath}/${order.id}`);
  }

  return (
    <div className="mx-auto max-w-xl px-4 py-10 sm:px-6 sm:py-16 lg:px-8">
      <motion.header
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35 }}
        className="text-center"
      >
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#c9a227]">Track Order</p>
        <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
          Where is my order?
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-zinc-500">
          Enter your order ID or order number to see live status, delivery estimate, and item
          details.
        </p>
      </motion.header>

      <motion.article
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, delay: 0.08 }}
        className="mt-10 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_8px_32px_rgba(0,0,0,0.06)] sm:p-8"
      >
        <form onSubmit={handleSubmit} className="space-y-4" noValidate>
          <div>
            <label htmlFor="track-order-query" className="text-xs font-bold uppercase tracking-wide text-zinc-500">
              Order ID or number
            </label>
            <input
              id="track-order-query"
              type="search"
              inputMode="search"
              autoComplete="off"
              autoFocus
              value={query}
              onChange={(event) => {
                setQuery(event.target.value);
                if (error) setError(null);
              }}
              placeholder={EXAMPLE_HINT}
              className="mt-2 w-full rounded-xl border border-zinc-200 bg-zinc-50/50 px-4 py-3.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-[#c9a227] focus:bg-white focus:ring-2 focus:ring-[#c9a227]/20"
              aria-invalid={error ? true : undefined}
              aria-describedby={error ? "track-order-error" : "track-order-hint"}
            />
            <p id="track-order-hint" className="mt-2 text-xs text-zinc-400">
              Find this on your confirmation email or order success page.
            </p>
          </div>

          {error ? (
            <motion.p
              id="track-order-error"
              role="alert"
              initial={{ opacity: 0, y: -4 }}
              animate={{ opacity: 1, y: 0 }}
              className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
            >
              {error}
            </motion.p>
          ) : null}

          <button
            type="submit"
            disabled={isSubmitting}
            className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isSubmitting ? "Looking up…" : "Track My Order"}
          </button>
        </form>

        <div className="mt-8 border-t border-zinc-100 pt-6">
          <p className="text-xs font-bold uppercase tracking-wide text-zinc-500">Need help?</p>
          <ul className="mt-3 space-y-2 text-sm text-zinc-600">
            <li>
              <Link href="/orders" className="font-semibold text-[#8b6914] hover:text-[#c9a227]">
                View your order history
              </Link>{" "}
              if you&apos;re signed in.
            </li>
            <li>
              Contact support at{" "}
              <a
                href="mailto:hello@chinaordertz.com"
                className="font-semibold text-[#8b6914] hover:text-[#c9a227]"
              >
                hello@chinaordertz.com
              </a>
            </li>
          </ul>
        </div>
      </motion.article>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.2 }}
        className="mt-8 text-center"
      >
        <Link
          href="/"
          className="text-sm font-semibold text-zinc-500 transition hover:text-zinc-900"
        >
          ← Back to shop
        </Link>
      </motion.div>
    </div>
  );
}
