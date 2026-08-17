import { z } from 'zod';

/**
 * Laravel `decimal:2` (and money accessors) serialize as JSON strings.
 * Web admin already accepts number|string at the client boundary.
 * Normalize here for display — do not perform commerce math.
 */

const DECIMAL_STRING = /^-?\d+(\.\d+)?$/;

function rejectMoney(ctx: z.RefinementCtx, message: string): typeof z.NEVER {
  ctx.addIssue({ code: z.ZodIssueCode.custom, message });
  return z.NEVER;
}

/**
 * Transport → finite number for UI (toLocaleString, etc.).
 * Accepts JSON number or Laravel decimal string. Rejects empty / malformed strings.
 */
export function normalizeMoneyTransport(value: unknown): number {
  if (typeof value === 'number') {
    if (!Number.isFinite(value)) {
      throw new Error('Invalid money number');
    }
    return value;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed === '' || !DECIMAL_STRING.test(trimmed)) {
      throw new Error('Invalid money string');
    }
    const parsed = Number(trimmed);
    if (!Number.isFinite(parsed)) {
      throw new Error('Invalid money string');
    }
    return parsed;
  }
  throw new Error('Invalid money value');
}

/** Required money field (number or decimal string → number). */
export const moneySchema = z.unknown().transform((value, ctx) => {
  try {
    return normalizeMoneyTransport(value);
  } catch {
    return rejectMoney(ctx, 'Expected money as number or decimal string');
  }
});

/** Optional money (undefined omitted; present values must be valid). */
export const optionalMoneySchema = moneySchema.optional();

/**
 * Optional + nullable money for nested fields the API may emit as null
 * (e.g. order item unit_price / line_total, payment amount).
 */
export const nullishMoneySchema = moneySchema.nullish();
