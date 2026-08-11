/**
 * Backend commerce channel codes — do not rename.
 * @see docs/mobile-api-contract-v1.md
 */
export const COMMERCE_JOURNEYS = ['CHINA_IMPORT', 'TZ_LOCAL'] as const;

export type CommerceJourney = (typeof COMMERCE_JOURNEYS)[number];

export function isCommerceJourney(value: unknown): value is CommerceJourney {
  return value === 'CHINA_IMPORT' || value === 'TZ_LOCAL';
}
