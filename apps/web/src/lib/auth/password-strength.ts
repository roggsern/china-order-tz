export type PasswordStrength = "weak" | "medium" | "strong";

export function getPasswordStrength(password: string): PasswordStrength | null {
  if (!password) {
    return null;
  }

  let score = 0;

  if (password.length >= 8) {
    score += 1;
  }

  if (password.length >= 12) {
    score += 1;
  }

  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
    score += 1;
  }

  if (/\d/.test(password)) {
    score += 1;
  }

  if (/[^A-Za-z0-9]/.test(password)) {
    score += 1;
  }

  if (score <= 2) {
    return "weak";
  }

  if (score <= 4) {
    return "medium";
  }

  return "strong";
}

export const PASSWORD_STRENGTH_META: Record<
  PasswordStrength,
  { label: string; barClass: string; textClass: string }
> = {
  weak: {
    label: "Weak",
    barClass: "bg-red-500",
    textClass: "text-red-400",
  },
  medium: {
    label: "Medium",
    barClass: "bg-amber-500",
    textClass: "text-amber-400",
  },
  strong: {
    label: "Strong",
    barClass: "bg-emerald-500",
    textClass: "text-emerald-400",
  },
};

export function getPasswordStrengthWidth(strength: PasswordStrength): string {
  switch (strength) {
    case "weak":
      return "33%";
    case "medium":
      return "66%";
    case "strong":
      return "100%";
  }
}
