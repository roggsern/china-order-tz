"use client";

import type { ReactNode } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { ANIMATION_TIMING, BRAND_COLORS, EASE_PREMIUM } from "./constants";

type AnimatedShimmerProps = {
  className?: string;
  children: ReactNode;
};

export function AnimatedShimmer({ className = "", children }: AnimatedShimmerProps) {
  const reduceMotion = useReducedMotion();

  const shimmerTransition = reduceMotion
    ? { duration: 0 }
    : {
        delay: ANIMATION_TIMING.shimmer.delay,
        duration: ANIMATION_TIMING.shimmer.duration,
        ease: EASE_PREMIUM,
      };

  return (
    <div className={`relative overflow-hidden ${className}`}>
      {children}
      {!reduceMotion && (
        <motion.div
          className="pointer-events-none absolute inset-0"
          initial={{ x: "-120%" }}
          animate={{ x: "120%" }}
          transition={shimmerTransition}
          aria-hidden
        >
          <div
            className="h-full w-1/2 skew-x-[-18deg] opacity-60"
            style={{
              background: `linear-gradient(90deg, transparent 0%, ${BRAND_COLORS.goldLight}40 45%, ${BRAND_COLORS.goldLight}90 50%, ${BRAND_COLORS.goldLight}40 55%, transparent 100%)`,
            }}
          />
        </motion.div>
      )}
    </div>
  );
}
