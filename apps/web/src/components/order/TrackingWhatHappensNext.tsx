"use client";

import type { CustomerTrackingDisplayStep } from "@/lib/order/tracking-display";
import { getTrackingWhatHappensNext } from "@/lib/order/tracking-display";

interface TrackingWhatHappensNextProps {
  timeline: CustomerTrackingDisplayStep[];
  tone?: "light" | "dark";
  className?: string;
}

export function TrackingWhatHappensNext({
  timeline,
  tone = "light",
  className = "",
}: TrackingWhatHappensNextProps) {
  const guidance = getTrackingWhatHappensNext(timeline);
  const isDark = tone === "dark";

  return (
    <section
      aria-labelledby="what-happens-next-heading"
      className={`rounded-2xl border p-5 sm:p-6 ${
        isDark
          ? "border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/10 via-zinc-900 to-zinc-950"
          : "border-[#c9a227]/25 bg-gradient-to-br from-[#c9a227]/10 via-white to-zinc-50"
      } ${className}`}
    >
      <p
        className={`text-[11px] font-bold uppercase tracking-[0.14em] ${
          isDark ? "text-[#e8c547]" : "text-[#8b6914]"
        }`}
      >
        What happens next
      </p>
      <h3
        id="what-happens-next-heading"
        className={`mt-2 text-lg font-bold ${isDark ? "text-zinc-50" : "text-zinc-900"}`}
      >
        {guidance.title}
      </h3>
      <p
        className={`mt-2 text-sm leading-relaxed ${
          isDark ? "text-zinc-300" : "text-zinc-600"
        }`}
      >
        {guidance.body}
      </p>
      <p
        className={`mt-4 text-sm ${isDark ? "text-zinc-400" : "text-zinc-500"}`}
      >
        Estimated duration:{" "}
        <span className={`font-semibold ${isDark ? "text-[#e8c547]" : "text-zinc-900"}`}>
          {guidance.estimatedDuration}
        </span>
      </p>
    </section>
  );
}
