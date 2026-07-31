export type EmailChangeFormValues = {
  newEmail: string;
  currentPassword: string;
};

export function validateEmailChangeForm(values: EmailChangeFormValues): string | null {
  const email = values.newEmail.trim();
  if (!email) {
    return "Please enter your new email address.";
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return "Please enter a valid email address.";
  }
  if (!values.currentPassword.trim()) {
    return "Please enter your current password.";
  }
  return null;
}

export function mapEmailChangeRequestSuccess(message?: string | null): string {
  return (
    message?.trim() ||
    "We sent a confirmation link to your new email address."
  );
}

export function mapEmailChangeConfirmSuccess(message?: string | null): string {
  return message?.trim() || "Your email address has been updated.";
}

export function mapEmailChangeError(message?: string | null): string {
  return message?.trim() || "Unable to change email. Please try again.";
}
