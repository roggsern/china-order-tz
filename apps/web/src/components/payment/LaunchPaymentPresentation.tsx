"use client";

import { LAUNCH_CHECKOUT_PAYMENT } from "@/lib/payment/constants";

interface LaunchPaymentPresentationProps {
  className?: string;
}

export function LaunchPaymentPresentation({ className = "" }: LaunchPaymentPresentationProps) {
  return (
    <div className={`space-y-4 ${className}`}>
      <div className="rounded-2xl border border-[#c9a227]/35 bg-[#c9a227]/5 p-5 shadow-sm">
        <div className="flex items-start gap-4">
          <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#c9a227]/20 bg-white text-2xl shadow-sm">
            🏦
          </span>
          <div className="min-w-0 flex-1">
            <h3 className="text-base font-bold text-zinc-900">{LAUNCH_CHECKOUT_PAYMENT.title}</h3>
            <p className="mt-1 text-sm leading-relaxed text-zinc-600">
              {LAUNCH_CHECKOUT_PAYMENT.description}
            </p>
            <p className="mt-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">
              Powered by{" "}
              <span className="text-[#8b6914]">{LAUNCH_CHECKOUT_PAYMENT.poweredBy}</span>
            </p>
          </div>
        </div>
      </div>

      <p className="text-xs leading-relaxed text-zinc-500">
        You&apos;ll complete payment securely through NMB on the next step.
      </p>
    </div>
  );
}
