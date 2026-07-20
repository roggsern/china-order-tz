import {
  parsePhoneNumberFromString,
  type CountryCode,
} from "libphonenumber-js/max";

export const PHONE_VALIDATION_MESSAGE =
  "Enter a valid phone number. Examples: 0712345678, 0657123456, or +255712345678.";

export const DEFAULT_PHONE_COUNTRY: CountryCode = "TZ";

export const SUPPORTED_PHONE_COUNTRY_CODES: CountryCode[] = [
  "TZ",
  "KE",
  "UG",
  "RW",
  "BI",
  "CD",
  "ZM",
];

function parsePhoneInput(rawInput: string, defaultCountry?: CountryCode) {
  const trimmed = rawInput.trim();
  if (!trimmed) {
    return undefined;
  }

  if (trimmed.startsWith("+")) {
    return parsePhoneNumberFromString(trimmed);
  }

  const country = defaultCountry ?? DEFAULT_PHONE_COUNTRY;
  const national = parsePhoneNumberFromString(trimmed, country);
  if (national) {
    return national;
  }

  const digits = trimmed.replace(/\D/g, "");
  if (digits.length >= 10) {
    return parsePhoneNumberFromString(`+${digits}`);
  }

  return undefined;
}

export function isValidPhoneNumber(
  rawInput: string,
  defaultCountry: CountryCode = DEFAULT_PHONE_COUNTRY,
): boolean {
  const parsed = parsePhoneInput(rawInput, defaultCountry);
  return parsed?.isValid() ?? false;
}

export function normalizePhoneToE164(
  rawInput: string,
  defaultCountry: CountryCode = DEFAULT_PHONE_COUNTRY,
): string | null {
  const parsed = parsePhoneInput(rawInput, defaultCountry);
  if (!parsed?.isValid()) {
    return null;
  }

  return parsed.format("E.164");
}

/** Formats valid numbers to E.164 on blur; returns trimmed input when still incomplete. */
export function formatPhoneOnBlur(
  rawInput: string,
  defaultCountry: CountryCode = DEFAULT_PHONE_COUNTRY,
): string {
  return normalizePhoneToE164(rawInput, defaultCountry) ?? rawInput.trim();
}

export function isValidPhoneForCountry(countryIso: string, rawInput: string): boolean {
  return isValidPhoneNumber(rawInput, countryIso as CountryCode);
}

export function normalizePhoneForBackend(countryIso: string, rawInput: string): string {
  return normalizePhoneToE164(rawInput, countryIso as CountryCode) ?? "";
}

/**
 * Payment gateways (M-Pesa, NMB, Selcom) expect digits without the leading +.
 * Example: +255712345678 -> 255712345678
 */
export function toPaymentGatewayPhone(phone: string): string {
  const e164 = normalizePhoneToE164(phone);
  if (e164) {
    return e164.replace(/\D/g, "");
  }

  return phone.replace(/\D/g, "");
}
