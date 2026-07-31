export type ForgotPasswordFormState = {
  email: string;
  error: string | null;
  successMessage: string | null;
  isSubmitting: boolean;
};

export type ResetPasswordFormState = {
  email: string;
  token: string;
  password: string;
  passwordConfirmation: string;
  error: string | null;
  successMessage: string | null;
  isSubmitting: boolean;
};

export function validateForgotPasswordEmail(email: string): string | null {
  const trimmed = email.trim();
  if (!trimmed) {
    return "Please enter your email address.";
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
    return "Please enter a valid email address.";
  }
  return null;
}

export function validateResetPasswordForm(input: {
  email: string;
  token: string;
  password: string;
  passwordConfirmation: string;
}): string | null {
  if (!input.token.trim()) {
    return "This reset link is missing a token. Request a new password reset email.";
  }
  const emailError = validateForgotPasswordEmail(input.email);
  if (emailError) {
    return emailError;
  }
  if (!input.password || input.password.length < 8) {
    return "Password must be at least 8 characters.";
  }
  if (input.password !== input.passwordConfirmation) {
    return "Password confirmation does not match.";
  }
  return null;
}

export function mapForgotPasswordSuccess(message?: string | null): string {
  return (
    message?.trim() ||
    "If an account exists for that email, password reset instructions have been sent."
  );
}

export function mapResetPasswordSuccess(message?: string | null): string {
  return (
    message?.trim() ||
    "Your password has been reset. You can sign in with your new password."
  );
}

export function parseResetPasswordQuery(searchParams: {
  get: (key: string) => string | null;
}): { email: string; token: string } {
  return {
    email: (searchParams.get("email") ?? "").trim(),
    token: (searchParams.get("token") ?? "").trim(),
  };
}
