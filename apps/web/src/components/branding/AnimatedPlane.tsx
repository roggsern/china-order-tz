"use client";

import { motion, useReducedMotion } from "framer-motion";
import { ANIMATION_TIMING, BRAND_COLORS, EASE_PREMIUM } from "./constants";

type AnimatedPlaneProps = {
  className?: string;
};

/** Keyframe positions approximating the curved gold route (viewBox 320×160) */
const PLANE_PATH = [
  { x: 72, y: 88, rotate: -18 },
  { x: 108, y: 58, rotate: -8 },
  { x: 148, y: 52, rotate: 4 },
  { x: 188, y: 58, rotate: 12 },
  { x: 228, y: 66, rotate: 18 },
  { x: 248, y: 68, rotate: 22 },
];

export function AnimatedPlane({ className = "" }: AnimatedPlaneProps) {
  const reduceMotion = useReducedMotion();

  const planeTransition = reduceMotion
    ? { duration: 0 }
    : {
        delay: ANIMATION_TIMING.plane.delay,
        duration: ANIMATION_TIMING.plane.duration,
        ease: EASE_PREMIUM,
        times: [0, 0.2, 0.45, 0.65, 0.85, 1],
      };

  const finalPos = PLANE_PATH[PLANE_PATH.length - 1];

  return (
    <svg
      viewBox="0 0 320 160"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden
    >
      <motion.g
        initial={{
          opacity: reduceMotion ? 1 : 0,
          x: reduceMotion ? finalPos.x : PLANE_PATH[0].x,
          y: reduceMotion ? finalPos.y : PLANE_PATH[0].y,
          rotate: reduceMotion ? finalPos.rotate : PLANE_PATH[0].rotate,
        }}
        animate={{
          opacity: 1,
          x: PLANE_PATH.map((p) => p.x),
          y: PLANE_PATH.map((p) => p.y),
          rotate: PLANE_PATH.map((p) => p.rotate),
        }}
        transition={planeTransition}
        style={{ transformOrigin: "center" }}
      >
        <path
          d="M -10 0 L 6 -3 L 14 0 L 6 3 Z"
          fill={BRAND_COLORS.white}
          fillOpacity={0.95}
        />
        <path
          d="M -2 -5 L 4 -2 L 4 2 L -2 5 Z"
          fill={BRAND_COLORS.goldLight}
          fillOpacity={0.8}
        />
        <path
          d="M -8 0 L -12 -4 L -12 4 Z"
          fill={BRAND_COLORS.gold}
          fillOpacity={0.7}
        />
      </motion.g>
    </svg>
  );
}

/** Cargo ship beneath the shipping route */
export function AnimatedShip({ className = "" }: AnimatedPlaneProps) {
  const reduceMotion = useReducedMotion();

  const shipTransition = reduceMotion
    ? { duration: 0 }
    : {
        delay: ANIMATION_TIMING.ship.delay,
        duration: ANIMATION_TIMING.ship.duration,
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
      <motion.g
        initial={{ opacity: reduceMotion ? 1 : 0, y: reduceMotion ? 0 : 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={shipTransition}
      >
        <path
          d="M 118 108 L 128 100 L 198 100 L 208 108 L 208 114 L 118 114 Z"
          fill={BRAND_COLORS.goldDark}
          fillOpacity={0.5}
        />
        <rect x="132" y="92" width="12" height="8" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.7} />
        <rect x="148" y="88" width="12" height="12" rx="1" fill={BRAND_COLORS.goldLight} fillOpacity={0.6} />
        <rect x="164" y="92" width="12" height="8" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.7} />
        <rect x="180" y="90" width="10" height="10" rx="1" fill={BRAND_COLORS.goldLight} fillOpacity={0.5} />
        <path
          d="M 118 114 Q 163 118 208 114"
          stroke={BRAND_COLORS.gold}
          strokeWidth="1"
          strokeOpacity={0.4}
          fill="none"
        />
      </motion.g>
    </svg>
  );
}
