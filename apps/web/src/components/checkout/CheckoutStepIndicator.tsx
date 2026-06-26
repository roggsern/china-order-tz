"use client";

import Link from "next/link";
import { hasValidCheckoutDraft } from "@/lib/checkout/draft";

export type CheckoutStep = "cart" | "checkout" | "payment" | "success";

const STEPS: { id: CheckoutStep; label: string; href?: string; requiresDraft?: boolean }[] = [
  { id: "cart", label: "Cart", href: "/cart" },
  { id: "checkout", label: "Checkout", href: "/checkout" },
  { id: "payment", label: "Payment", href: "/checkout/payment", requiresDraft: true },
  { id: "success", label: "Confirmation" },
];

interface CheckoutStepIndicatorProps {
  current: CheckoutStep;
}

function canNavigateToStep(step: (typeof STEPS)[number], currentIndex: number, index: number): boolean {
  if (index > currentIndex) {
    return false;
  }

  if (step.requiresDraft && !hasValidCheckoutDraft()) {
    return false;
  }

  return Boolean(step.href);
}

export function CheckoutStepIndicator({ current }: CheckoutStepIndicatorProps) {
  const currentIndex = STEPS.findIndex((step) => step.id === current);

  return (
    <nav aria-label="Checkout progress" className="mt-6">
      <ol className="flex flex-wrap items-center gap-2 sm:gap-3">
        {STEPS.map((step, index) => {
          const isComplete = index < currentIndex;
          const isCurrent = step.id === current;
          const isUpcoming = index > currentIndex;

          const content = (
            <>
              <span
                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                  isComplete
                    ? "bg-emerald-100 text-emerald-700"
                    : isCurrent
                      ? "bg-[#c9a227] text-zinc-900"
                      : "bg-zinc-100 text-zinc-400"
                }`}
                aria-hidden
              >
                {isComplete ? "✓" : index + 1}
              </span>
              <span
                className={`text-sm font-semibold ${
                  isCurrent ? "text-zinc-900" : isUpcoming ? "text-zinc-400" : "text-zinc-600"
                }`}
              >
                {step.label}
              </span>
            </>
          );

          return (
            <li key={step.id} className="flex items-center gap-2 sm:gap-3">
              {step.href && canNavigateToStep(step, currentIndex, index) ? (
                <Link
                  href={step.href}
                  className="inline-flex items-center gap-2 transition hover:opacity-80"
                  aria-current={isCurrent ? "step" : undefined}
                >
                  {content}
                </Link>
              ) : (
                <span
                  className="inline-flex items-center gap-2"
                  aria-current={isCurrent ? "step" : undefined}
                >
                  {content}
                </span>
              )}
              {index < STEPS.length - 1 ? (
                <span className="hidden h-px w-6 bg-zinc-200 sm:block" aria-hidden />
              ) : null}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
