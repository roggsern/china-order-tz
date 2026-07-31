export type ChangePasswordFormValues = {
  currentPassword: string;
  password: string;
  passwordConfirmation: string;
};

export function validateChangePasswordForm(values: ChangePasswordFormValues): string | null {
  if (!values.currentPassword.trim()) {
    return "Please enter your current password.";
  }
  if (!values.password || values.password.length < 8) {
    return "New password must be at least 8 characters.";
  }
  if (values.password !== values.passwordConfirmation) {
    return "Password confirmation does not match.";
  }
  if (values.password === values.currentPassword) {
    return "New password must be different from your current password.";
  }
  return null;
}

export function mapChangePasswordSuccess(message?: string | null): string {
  return (
    message?.trim() ||
    "Your password has been changed. Please sign in again."
  );
}

export function mapChangePasswordError(message?: string | null): string {
  return message?.trim() || "Unable to change password. Please try again.";
}
