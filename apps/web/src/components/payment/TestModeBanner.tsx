interface TestModeBannerProps {
  className?: string;
}

export function TestModeBanner({ className = "" }: TestModeBannerProps) {
  return (
    <div
      role="status"
      className={`rounded-2xl border border-[#c9a227]/40 bg-gradient-to-r from-[#c9a227]/10 to-[#e8c547]/10 px-4 py-3.5 sm:px-5 ${className}`}
    >
      <div className="flex items-start gap-3">
        <span
          className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#c9a227]/20 text-sm"
          aria-hidden
        >
          🧪
        </span>
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#8b6914]">
            Test mode — no real money involved
          </p>
          <p className="mt-1 text-sm leading-relaxed text-zinc-600">
            Payments are simulated for development. Use{" "}
            <span className="font-semibold text-zinc-800">Simulate Payment</span> to complete checkout
            instantly, or use M-Pesa STK for async test flow.
          </p>
        </div>
      </div>
    </div>
  );
}
