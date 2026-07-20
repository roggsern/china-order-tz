/**
 * @deprecated Import from `@/lib/phone` instead.
 * Re-exports kept for gradual migration of existing imports.
 */
export {
  PHONE_VALIDATION_MESSAGE as TZ_PHONE_VALIDATION_MESSAGE,
  DEFAULT_PHONE_COUNTRY,
  isValidPhoneNumber as isValidTanzaniaPhone,
  formatPhoneOnBlur as formatTanzaniaPhone,
  normalizePhoneToE164,
} from "@/lib/phone";

import { normalizePhoneToE164 } from "@/lib/phone";

/** @deprecated Use normalizePhoneToE164 — stores E.164 instead of local 07 format. */
export function toLocalTanzaniaPhone(input: string): string {
  return normalizePhoneToE164(input) ?? input.trim();
}
