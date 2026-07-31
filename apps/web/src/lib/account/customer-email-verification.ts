export type VerifyEmailQuery = {
  id: string;
  hash: string;
  expires: string;
  signature: string;
};

export function parseVerifyEmailQuery(searchParams: {
  get: (key: string) => string | null;
}): VerifyEmailQuery {
  return {
    id: (searchParams.get("id") ?? "").trim(),
    hash: (searchParams.get("hash") ?? "").trim(),
    expires: (searchParams.get("expires") ?? "").trim(),
    signature: (searchParams.get("signature") ?? "").trim(),
  };
}

export function validateVerifyEmailQuery(query: VerifyEmailQuery): string | null {
  if (!query.id || !query.hash || !query.expires || !query.signature) {
    return "This verification link is incomplete. Request a new verification email.";
  }
  return null;
}

export function mapVerifyEmailSuccess(message?: string | null, alreadyVerified?: boolean): string {
  if (message?.trim()) return message.trim();
  return alreadyVerified
    ? "Your email is already verified."
    : "Your email address has been verified.";
}

export function mapVerifyEmailError(message?: string | null): string {
  return message?.trim() || "Unable to verify email. The link may be invalid or expired.";
}

export function mapResendVerificationSuccess(message?: string | null): string {
  return message?.trim() || "A verification link has been sent to your email address.";
}
