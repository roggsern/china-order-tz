/**
 * Customer-facing authentication copy.
 * Maps technical auth errors into friendly invitations.
 */

export type AuthInviteContext =
  | "checkout"
  | "wishlist"
  | "orders"
  | "reviews"
  | "notifications"
  | "account"
  | "generic";

export type AuthInviteCopy = {
  title: string;
  description: string;
  icon: "lock" | "heart" | "package" | "star" | "bell" | "user";
};

const INVITE_COPY: Record<AuthInviteContext, AuthInviteCopy> = {
  checkout: {
    title: "You're almost there!",
    description:
      "Sign in or create your account to complete your order. Your cart has already been saved.",
    icon: "lock",
  },
  wishlist: {
    title: "Save what you love",
    description: "Sign in to save products to your wishlist.",
    icon: "heart",
  },
  orders: {
    title: "Your orders live here",
    description: "Sign in to view your order history.",
    icon: "package",
  },
  reviews: {
    title: "Share your experience",
    description: "Sign in to write a review after your purchase.",
    icon: "star",
  },
  notifications: {
    title: "Stay in the loop",
    description: "Sign in to receive alerts and order updates.",
    icon: "bell",
  },
  account: {
    title: "You're one step away!",
    description: "Sign in to continue where you left off.",
    icon: "user",
  },
  generic: {
    title: "You're one step away!",
    description: "Sign in to continue where you left off.",
    icon: "lock",
  },
};

export function getAuthInviteCopy(context: AuthInviteContext): AuthInviteCopy {
  return INVITE_COPY[context];
}

const TECHNICAL_PATTERNS: Array<{ pattern: RegExp; friendly: string }> = [
  {
    pattern: /unauthenticated|authentication required|auth(entication)?\s+required/i,
    friendly: "Please sign in to continue.",
  },
  {
    pattern: /unauthorized|permission denied|forbidden/i,
    friendly: "Please sign in to continue.",
  },
  {
    pattern: /you must be signed in to (view your orders|view this order)/i,
    friendly: "Sign in to view your order history.",
  },
  {
    pattern: /you must be signed in to complete checkout/i,
    friendly:
      "You're almost there! Sign in or create your account to complete your order.",
  },
  {
    pattern: /you must be signed in to prepare payment|you must be signed in to pay/i,
    friendly: "Please sign in to continue with payment.",
  },
  {
    pattern: /please sign in to complete checkout/i,
    friendly:
      "You're almost there! Sign in or create your account to complete your order. Your cart has already been saved.",
  },
];

/**
 * Replace technical auth wording with customer-friendly language.
 * Non-auth errors are returned unchanged (after trim).
 */
export function toFriendlyAuthMessage(
  message: string | null | undefined,
  fallback = "Please sign in to continue.",
): string {
  const trimmed = message?.trim();
  if (!trimmed) return fallback;

  for (const { pattern, friendly } of TECHNICAL_PATTERNS) {
    if (pattern.test(trimmed)) {
      return friendly;
    }
  }

  return trimmed;
}

export function isAuthRequiredMessage(message: string | null | undefined): boolean {
  if (!message?.trim()) return false;
  return /unauthenticated|unauthorized|forbidden|permission denied|authentication required|must be signed in|please sign in/i.test(
    message,
  );
}
