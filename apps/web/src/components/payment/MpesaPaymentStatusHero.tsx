"use client";

import { motion } from "framer-motion";
import type { StkFlowPhase } from "@/lib/payment/stk-flow";

interface MpesaPaymentStatusHeroProps {
  phase: StkFlowPhase;
  headline: string;
  subtext: string;
  testMode?: boolean;
}

function SuccessIcon() {
  return (
    <motion.div
      initial={{ scale: 0.5, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      transition={{ type: "spring", stiffness: 260, damping: 18 }}
      className="relative mx-auto flex h-24 w-24 items-center justify-center"
    >
      <motion.span
        className="absolute inset-0 rounded-full bg-emerald-500/20"
        animate={{ scale: [1, 1.15, 1], opacity: [0.6, 0, 0.6] }}
        transition={{ duration: 2, repeat: Infinity }}
        aria-hidden
      />
      <span className="relative flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-4xl text-white shadow-lg shadow-emerald-500/30">
        ✓
      </span>
    </motion.div>
  );
}

function FailedIcon() {
  return (
    <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-500/15 text-4xl text-red-400 ring-1 ring-red-500/30">
      ✕
    </div>
  );
}

export function MpesaPaymentStatusHero({
  phase,
  headline,
  subtext,
  testMode,
}: MpesaPaymentStatusHeroProps) {
  return (
    <motion.div
      key={phase}
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      transition={{ duration: 0.35, ease: "easeOut" }}
      className="text-center"
    >
      {phase === "success" ? (
        <SuccessIcon />
      ) : phase === "failed" ? (
        <FailedIcon />
      ) : (
        <div className="relative mx-auto h-24 w-24">
          <motion.span
            className="absolute inset-0 rounded-full border-2 border-[#c9a227]/20"
            aria-hidden
          />
          <motion.span
            className="absolute inset-0 rounded-full border-2 border-transparent border-t-[#c9a227] border-r-[#e8c547]"
            animate={{ rotate: 360 }}
            transition={{ duration: 1.1, repeat: Infinity, ease: "linear" }}
            aria-hidden
          />
          <motion.span
            className="absolute inset-2 rounded-full bg-[#c9a227]/10"
            animate={{ scale: [1, 1.06, 1], opacity: [0.5, 0.85, 0.5] }}
            transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
            aria-hidden
          />
          <span className="absolute inset-0 flex items-center justify-center text-3xl" aria-hidden>
            📱
          </span>
        </div>
      )}

      <p className="mt-6 text-[11px] font-bold uppercase tracking-[0.2em] text-[#c9a227]">
        {testMode ? "M-Pesa · Test Mode" : "M-Pesa STK Push"}
      </p>

      <h1 className="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">{headline}</h1>

      <p className="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-zinc-400">{subtext}</p>
    </motion.div>
  );
}
