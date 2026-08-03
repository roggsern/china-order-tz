import {
  shouldShowGuestAuthActions,
  type StorefrontNavAudience,
} from "./navigation-policy";
import type { AccountMenuPreset } from "./account-menu-config";

export type AccountMenuGuestBehavior = "link" | "menu";

export type AccountMenuPresentation = {
  guestBehavior: AccountMenuGuestBehavior;
  preset: AccountMenuPreset;
  showLabel: boolean;
};

export function isAccountMenuPresentation(
  presentation: AccountMenuPresentation | { kind: "guest-links" },
): presentation is AccountMenuPresentation {
  return !("kind" in presentation);
}

export function resolveAccountMenuPresentation(
  audience: StorefrontNavAudience,
  variant: "desktop" | "mobile",
): AccountMenuPresentation | { kind: "guest-links" } {
  if (shouldShowGuestAuthActions(audience)) {
    if (variant === "mobile") {
      return {
        guestBehavior: "menu",
        preset: "header",
        showLabel: false,
      };
    }

    return { kind: "guest-links" };
  }

  return {
    guestBehavior: "link",
    preset: variant === "mobile" ? "header" : "full",
    showLabel: variant === "desktop",
  };
}
