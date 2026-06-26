const TZ_NATIONAL_DIGITS = 9;

/** Valid Tanzanian mobile operator prefixes (first two digits of the national number). */
const TZ_MOBILE_PREFIXES = new Set([
  "62",
  "65",
  "67",
  "68",
  "69",
  "71",
  "73",
  "74",
  "75",
  "76",
  "77",
  "78",
]);

export const TZ_PHONE_VALIDATION_MESSAGE =
  "Enter a valid Tanzanian mobile number. Example: 0712345678 or +255712345678.";

export function normalizeTanzaniaPhoneDigits(input: string): string {
  let digits = input.replace(/\D/g, "");

  if (digits.startsWith("255")) {
    digits = digits.slice(3);
  } else if (digits.startsWith("0")) {
    digits = digits.slice(1);
  }

  return digits.slice(0, TZ_NATIONAL_DIGITS);
}

function hasValidTzMobilePrefix(digits: string): boolean {
  if (digits.length < 2) {
    return false;
  }
  return TZ_MOBILE_PREFIXES.has(digits.slice(0, 2));
}

export function isValidTanzaniaPhone(input: string): boolean {
  const digits = normalizeTanzaniaPhoneDigits(input);
  return digits.length === TZ_NATIONAL_DIGITS && hasValidTzMobilePrefix(digits);
}

/** Normalize a complete valid number to E.164 (+255XXXXXXXXX). Returns trimmed input if incomplete/invalid. */
export function formatTanzaniaPhone(input: string): string {
  const digits = normalizeTanzaniaPhoneDigits(input);
  if (digits.length === TZ_NATIONAL_DIGITS && hasValidTzMobilePrefix(digits)) {
    return `+255${digits}`;
  }
  return input.trim();
}
