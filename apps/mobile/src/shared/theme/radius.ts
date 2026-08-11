/**
 * Corner radius tokens — match common mobile card / control radii.
 */
export const radius = {
  none: 0,
  sm: 6,
  md: 8,
  lg: 10,
  xl: 12,
  xxl: 16,
  full: 9999,
} as const;

export type RadiusKey = keyof typeof radius;
