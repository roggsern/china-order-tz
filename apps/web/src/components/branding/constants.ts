export const BRAND_COLORS = {
  gold: "#c9a227",
  goldLight: "#e8c547",
  goldDark: "#8b6914",
  black: "#0a0a0a",
  white: "#ffffff",
  chinaRed: "#de2910",
  chinaRedGlow: "rgba(222, 41, 16, 0.35)",
  tzGreen: "#1EB53A",
  tzYellow: "#FCD116",
  tzBlue: "#00A3DD",
  tzBlack: "#000000",
} as const;

/** Total animation ~4.5s before static end state */
export const ANIMATION_TIMING = {
  background: { delay: 0, duration: 0.6 },
  cart: { delay: 0.3, duration: 0.7 },
  china: { delay: 0.9, duration: 0.7 },
  tanzania: { delay: 1.3, duration: 0.6 },
  route: { delay: 1.6, duration: 0.5 },
  plane: { delay: 1.8, duration: 1.4 },
  ship: { delay: 2.4, duration: 0.6 },
  title: { delay: 2.8, duration: 0.8 },
  tagline: { delay: 3.4, duration: 0.5 },
  shimmer: { delay: 4.0, duration: 0.8 },
} as const;

export const EASE_PREMIUM = [0.22, 1, 0.36, 1] as const;

export const ROUTE_PATH =
  "M 72 88 Q 120 52 168 72 T 248 68";
