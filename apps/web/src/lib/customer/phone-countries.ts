export type PhoneCountry = {
  iso: string;
  name: string;
  flag: string;
  dialCode: string;
};

export const PHONE_COUNTRIES: PhoneCountry[] = [
  { iso: "TZ", name: "Tanzania", flag: "🇹🇿", dialCode: "+255" },
  { iso: "KE", name: "Kenya", flag: "🇰🇪", dialCode: "+254" },
  { iso: "UG", name: "Uganda", flag: "🇺🇬", dialCode: "+256" },
  { iso: "RW", name: "Rwanda", flag: "🇷🇼", dialCode: "+250" },
  { iso: "BI", name: "Burundi", flag: "🇧🇮", dialCode: "+257" },
  { iso: "CD", name: "DR Congo", flag: "🇨🇩", dialCode: "+243" },
  { iso: "ZM", name: "Zambia", flag: "🇿🇲", dialCode: "+260" },
];

export const DEFAULT_PHONE_COUNTRY_ISO = "TZ";

export function getPhoneCountry(iso: string): PhoneCountry {
  return PHONE_COUNTRIES.find((country) => country.iso === iso) ?? PHONE_COUNTRIES[0];
}
