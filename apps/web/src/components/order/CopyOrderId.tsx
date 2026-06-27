"use client";

import { useCallback, useState } from "react";

interface CopyOrderIdProps {
  orderId: string;
}

export function CopyOrderId({ orderId }: CopyOrderIdProps) {
  const [copied, setCopied] = useState(false);
  const shortId = orderId.slice(0, 8).toUpperCase();

  const handleCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(orderId);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  }, [orderId]);

  return (
    <div className="flex flex-wrap items-center gap-3">
      <span
        className="font-mono text-sm font-semibold tracking-wide text-zinc-700 sm:text-base"
        title={orderId}
      >
        {shortId}
      </span>
      <button
        type="button"
        onClick={handleCopy}
        className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-[#c9a227]/40 hover:bg-amber-50/50 hover:text-[#8b6914]"
        aria-label={`Copy order ID ${orderId}`}
      >
        <span aria-hidden>📋</span>
        {copied ? "Copied!" : "Copy ID"}
      </button>
    </div>
  );
}
