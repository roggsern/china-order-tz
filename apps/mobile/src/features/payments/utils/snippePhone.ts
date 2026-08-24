const TZ_MOBILE = /^255[67]\d{8}$/;

/** Normalize Tanzania mobile numbers the same way backend Snippe accepts. */
export function normalizeSnippePhone(phone: string): string | null {
  const digits = phone.replace(/\D+/g, '');
  if (!digits) return null;

  let normalized = digits;
  if (digits.startsWith('255')) {
    normalized = digits;
  } else if (digits.startsWith('0')) {
    normalized = `255${digits.slice(1)}`;
  } else if (digits.length === 9) {
    normalized = `255${digits}`;
  } else {
    return null;
  }

  return TZ_MOBILE.test(normalized) ? normalized : null;
}

/** Friendly local form for the payment input. Backend still accepts 07 / +255 / 255. */
export function formatSnippePhoneForInput(phone: string): string | null {
  const normalized = normalizeSnippePhone(phone);
  if (!normalized) return null;
  return `0${normalized.slice(3)}`;
}

export function validateSnippePhoneInput(phone: string): string | null {
  const trimmed = phone.trim();
  if (!trimmed) {
    return 'Enter your Mobile Money number.';
  }

  if (!normalizeSnippePhone(trimmed)) {
    return 'Enter a valid Tanzania mobile number, for example 0712345678 or +255712345678.';
  }

  return null;
}
