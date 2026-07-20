import { getPhoneCountry, type PhoneCountry } from "@/lib/customer/phone-countries";
import {
  isValidPhoneForCountry,
  normalizePhoneForBackend,
  PHONE_VALIDATION_MESSAGE,
} from "@/lib/phone";

export { isValidPhoneForCountry, normalizePhoneForBackend, PHONE_VALIDATION_MESSAGE };

export function formatPhoneCountryLabel(country: PhoneCountry): string {
  return `${country.flag} ${country.name} (${country.dialCode})`;
}

export function formatPhoneCountryCompact(country: PhoneCountry): string {
  return `${country.flag} ${country.dialCode}`;
}

export { getPhoneCountry };
