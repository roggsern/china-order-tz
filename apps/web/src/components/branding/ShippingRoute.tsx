"use client";

import { motion, useReducedMotion } from "framer-motion";
import { ANIMATION_TIMING, BRAND_COLORS, EASE_PREMIUM, ROUTE_PATH } from "./constants";

type ShippingRouteProps = {
  className?: string;
};

export function ShippingRoute({ className = "" }: ShippingRouteProps) {
  const reduceMotion = useReducedMotion();

  const routeTransition = reduceMotion
    ? { duration: 0 }
    : {
        delay: ANIMATION_TIMING.route.delay,
        duration: ANIMATION_TIMING.route.duration,
        ease: EASE_PREMIUM,
      };

  return (
    <svg
      viewBox="0 0 320 160"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden
    >
      <defs>
        <linearGradient id="route-gold" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor={BRAND_COLORS.goldDark} stopOpacity={0.4} />
          <stop offset="50%" stopColor={BRAND_COLORS.goldLight} stopOpacity={0.9} />
          <stop offset="100%" stopColor={BRAND_COLORS.gold} stopOpacity={0.5} />
        </linearGradient>
      </defs>

      <motion.path
        d={ROUTE_PATH}
        stroke="url(#route-gold)"
        strokeWidth="1.5"
        strokeDasharray="6 5"
        strokeLinecap="round"
        fill="none"
        initial={{ pathLength: reduceMotion ? 1 : 0, opacity: reduceMotion ? 0.7 : 0 }}
        animate={{ pathLength: 1, opacity: 0.7 }}
        transition={routeTransition}
      />
    </svg>
  );
}
