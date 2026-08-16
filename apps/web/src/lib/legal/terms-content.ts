import type { LegalSection } from "@/components/legal/LegalDocumentPage";
import { LEGAL_BRAND_NAME, LEGAL_SUPPORT_EMAIL } from "@/lib/legal/legal-paths";

export const TERMS_PAGE_TITLE = `Terms of Service — ${LEGAL_BRAND_NAME}`;
export const TERMS_PAGE_DESCRIPTION =
  "Terms for using the CHINA ORDER TZ website and apps, including Order from China and Buy from Tanzania shopping journeys.";

export const TERMS_INTRO =
  "These Terms of Service (“Terms”) govern your use of the CHINA ORDER TZ website, mobile applications, and related customer services. By creating an account or placing an order, you agree to these Terms. If you do not agree, do not use the services.";

export const TERMS_SECTIONS: LegalSection[] = [
  {
    id: "acceptance",
    title: "1. Acceptance of terms",
    paragraphs: [
      "By accessing or using CHINA ORDER TZ, you confirm that you can enter into these Terms and that the information you provide is accurate.",
      "Additional product, checkout, or journey-specific notices shown in the app or website form part of your agreement when you proceed.",
    ],
  },
  {
    id: "accounts",
    title: "2. Accounts and security",
    paragraphs: [
      "You are responsible for maintaining the confidentiality of your login credentials and for activity under your account.",
      "Provide accurate registration and contact details, and keep them updated so we can fulfill orders and communicate about your account.",
      "We may suspend or restrict accounts that appear compromised, abusive, fraudulent, or otherwise harmful to the platform or other customers.",
    ],
  },
  {
    id: "journeys",
    title: "3. Shopping journeys",
    paragraphs: [
      "CHINA ORDER TZ offers distinct commerce journeys, including:",
    ],
    bullets: [
      "Order from China (CHINA_IMPORT): catalog and checkout flows oriented to importing products from China through our platform processes.",
      "Buy from Tanzania (TZ_LOCAL): store-oriented shopping from Tanzania-based storefronts available on the platform.",
    ],
  },
  {
    id: "products",
    title: "4. Product information and availability",
    paragraphs: [
      "We aim to present product information accurately, including pricing and availability where shown by the platform.",
      "Catalog details, stock, configurations, and lead times can change. An item is not guaranteed until the order is accepted and payment/fulfillment conditions applicable to that journey are met.",
    ],
  },
  {
    id: "pricing-payment",
    title: "5. Pricing and payment",
    paragraphs: [
      "Prices and currency displays are shown in the storefront and checkout interfaces. Applicable totals are confirmed during checkout before you authorize payment.",
      "Payments are processed through integrated payment providers. Payment status and references are recorded by our systems; card entry and sensitive payment credentials are handled by the payment provider’s flows where those are used.",
      "An order may remain unpaid or cancelable according to platform rules until payment is confirmed by the payment orchestration and provider responses.",
    ],
  },
  {
    id: "fulfillment",
    title: "6. Fulfillment and delivery",
    paragraphs: [
      "Fulfillment steps, shipping coordination, and delivery options depend on the selected journey, product type, and operational status shown in your order tracking.",
      "Estimated timelines, when shown, are informational and may change due to suppliers, customs, carriers, or other operational factors. The platform’s order and tracking status remain the authoritative customer-facing status.",
    ],
  },
  {
    id: "cancellations-returns",
    title: "7. Cancellations, returns, and refunds",
    paragraphs: [
      "Where the platform provides cancellation, return, or refund request features, those flows and resulting statuses are governed by the platform’s order, return, and refund processes.",
      "Eligibility and outcomes depend on order state, product type, journey rules, and payment status. This document does not invent fixed refund windows or guaranteed outcomes beyond what the live order tools and support team communicate for a specific order.",
    ],
  },
  {
    id: "prohibited",
    title: "8. Prohibited use",
    paragraphs: ["You agree not to:"],
    bullets: [
      "Misuse the platform, attempt unauthorized access, or interfere with security or operations.",
      "Provide false identity or payment information, or engage in fraudulent ordering.",
      "Scrape, overload, or reverse engineer the services except as allowed by law.",
      "Use the services for unlawful purposes.",
    ],
  },
  {
    id: "ip",
    title: "9. Intellectual property",
    paragraphs: [
      "CHINA ORDER TZ branding, storefront content, software, and related materials are owned by us or our licensors. You receive a limited right to use the services for shopping and account management as intended.",
      "You may not copy or exploit platform content for commercial purposes without permission, except for ordinary personal use of product information while shopping.",
    ],
  },
  {
    id: "availability",
    title: "10. Service availability",
    paragraphs: [
      "We work to keep the services available, but maintenance, outages, or upstream provider issues may occur. We do not guarantee uninterrupted or error-free operation.",
    ],
  },
  {
    id: "liability",
    title: "11. Limitation of liability",
    paragraphs: [
      "To the fullest extent permitted by applicable law, CHINA ORDER TZ is not liable for indirect, incidental, special, consequential, or punitive damages, or for lost profits, arising from your use of the services.",
      "Nothing in these Terms excludes liability that cannot be excluded under applicable law.",
    ],
  },
  {
    id: "suspension",
    title: "12. Suspension and account closure",
    paragraphs: [
      "We may suspend or close accounts that violate these Terms or present risk to the platform, customers, or partners.",
      "Customer-initiated account closure capabilities may be offered through account settings as they become available. Transactional records may be retained as described in the Privacy Policy.",
    ],
  },
  {
    id: "changes",
    title: "13. Changes to these Terms",
    paragraphs: [
      "We may update these Terms periodically. The effective date on this page will be revised when updates are published. Continued use after changes means you should review the updated Terms.",
    ],
  },
  {
    id: "contact",
    title: "14. Contact",
    paragraphs: [
      `For questions about these Terms or your orders, contact ${LEGAL_SUPPORT_EMAIL}.`,
    ],
  },
];
