"use client";

import { useCallback, useState } from "react";

interface CopyOrderNumberProps {
  orderNumber: string;
}

export function CopyOrderNumber({ orderNumber }: CopyOrderNumberProps) {
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
      <span className="font-mono text-xl font-bold tracking-tight text-zinc-900 sm:text-2xl">
        [{orderNumber}]
      </span>
      <button
        type="button"
        onClick={handleCopy}
        className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-[#c9a227]/40 hover:bg-amber-50/50 hover:text-[#8b6914]"
        aria-label={`Copy order number ${orderNumber}`}
      >
        <span aria-hidden>📋</span>
        {copied ? "Copied!" : "Copy"}
      </button>
    </div>
  );
}
