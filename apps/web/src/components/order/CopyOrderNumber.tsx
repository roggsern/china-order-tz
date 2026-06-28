"use client";

import { useCallback, useState } from "react";

interface CopyOrderNumberProps {
  orderNumber: string;
  theme?: "light" | "dark";
}

export function CopyOrderNumber({ orderNumber, theme = "light" }: CopyOrderNumberProps) {
  const isDark = theme === "dark";
  const [copied, setCopied] = useState(false);

  const handleCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(orderNumber);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  }, [orderNumber]);

  return (
    <div className="flex flex-wrap items-center gap-3">
      <span
        className={`font-mono text-xl font-bold tracking-tight sm:text-2xl ${
          isDark ? "text-white" : "text-zinc-900"
        }`}
      >
        [{orderNumber}]
      </span>
      <button
        type="button"
        onClick={handleCopy}
        className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition ${
          isDark
            ? "border-zinc-700 bg-zinc-800 text-zinc-200 hover:border-[#c9a227]/40 hover:text-[#e8c547]"
            : "border-zinc-200 bg-white text-zinc-700 hover:border-[#c9a227]/40 hover:bg-amber-50/50 hover:text-[#8b6914]"
        }`}
        aria-label={`Copy order number ${orderNumber}`}
      >
        <span aria-hidden>📋</span>
        {copied ? "Copied!" : "Copy"}
      </button>
    </div>
  );
}
