export function validateCloseAccountForm(input: {
  currentPassword: string;
  acknowledge: boolean;
}): string | null {
  if (!input.currentPassword.trim()) {
    return "Enter your current password to close your account.";
  }
  if (!input.acknowledge) {
    return "Confirm that you understand account closure is permanent from this screen.";
  }
  return null;
}

export function mapCloseAccountError(message: string): string {
  const trimmed = message.trim();
  if (/current password is incorrect/i.test(trimmed)) {
    return "Current password is incorrect.";
  }
  return trimmed || "Unable to close your account. Please try again.";
}

export function mapCloseAccountSuccess(message?: string): string {
  return (
    message?.trim() ||
    "Your account has been closed. You have been signed out."
  );
}
