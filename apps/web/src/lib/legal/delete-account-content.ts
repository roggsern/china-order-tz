import type { LegalSection } from "@/components/legal/LegalDocumentPage";
import {
  LEGAL_BRAND_NAME,
  LEGAL_PATHS,
  LEGAL_SUPPORT_EMAIL,
} from "@/lib/legal/legal-paths";

export const DELETE_ACCOUNT_PAGE_TITLE = `Delete account — ${LEGAL_BRAND_NAME}`;
export const DELETE_ACCOUNT_PAGE_DESCRIPTION =
  "How to close or delete your CHINA ORDER TZ customer account in the app or on the website, and what records may be retained.";

export const DELETE_ACCOUNT_INTRO =
  "This page explains how signed-in customers can close their CHINA ORDER TZ account. It is an instructions and store-compliance surface. It does not delete accounts for visitors who are not signed in, and it never exposes private account data.";

export const DELETE_ACCOUNT_SECTIONS: LegalSection[] = [
  {
    id: "how-to-close",
    title: "1. How to close your account",
    paragraphs: [
      "Account closure is available only when you are signed in. Use one of these paths:",
    ],
    bullets: [
      "Mobile app (Android or iOS): open Account → Close account, confirm with your current password, and submit.",
      "Website: open Account → Security, complete the Close account section with your current password and confirmation, then submit.",
      "Support fallback: if you cannot sign in, contact support and we will help verify the request.",
    ],
  },
  {
    id: "what-happens",
    title: "2. What happens when you close",
    paragraphs: ["When closure completes:"],
    bullets: [
      "Signed-in access ends immediately and active sessions/tokens are revoked.",
      "Push-notification device tokens for the account are deactivated.",
      "Login identity (such as email used to sign in) is anonymized so it can no longer be used to sign in to the closed account.",
      "Disposable personal data such as saved address-book entries, active carts, wishlists, and notification preferences are removed or anonymized.",
    ],
  },
  {
    id: "what-may-be-retained",
    title: "3. What may be retained",
    paragraphs: [
      "Some records may need to remain for legitimate operational, accounting, dispute, audit, or legal purposes. Categories can include orders, payments, refunds, shipments, and related fulfillment history.",
      "This page does not state fixed retention periods in days or years.",
    ],
  },
  {
    id: "privacy",
    title: "4. Privacy Policy",
    paragraphs: [
      `For a fuller description of how we handle personal information, see our Privacy Policy at ${LEGAL_PATHS.privacy}.`,
    ],
  },
  {
    id: "contact",
    title: "5. Contact",
    paragraphs: [
      `Questions about account closure: ${LEGAL_SUPPORT_EMAIL}.`,
    ],
  },
];
