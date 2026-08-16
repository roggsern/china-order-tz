import type { LegalSection } from "@/components/legal/LegalDocumentPage";
import { LEGAL_BRAND_NAME, LEGAL_SUPPORT_EMAIL } from "@/lib/legal/legal-paths";

export const PRIVACY_PAGE_TITLE = `Privacy Policy — ${LEGAL_BRAND_NAME}`;
export const PRIVACY_PAGE_DESCRIPTION =
  "How CHINA ORDER TZ collects and uses account, order, payment-reference, device, and support information on our commerce platform.";

export const PRIVACY_INTRO =
  "This Privacy Policy explains how CHINA ORDER TZ (“we”, “us”) handles personal information when you use our website, mobile applications, and related customer services. It describes practices reflected in our current platform architecture. It is not legal advice and does not claim certification under any specific privacy law.";

export const PRIVACY_SECTIONS: LegalSection[] = [
  {
    id: "scope",
    title: "1. Scope",
    paragraphs: [
      "This policy applies to customer-facing CHINA ORDER TZ services, including Order from China (CHINA_IMPORT) and Buy from Tanzania (TZ_LOCAL) shopping journeys, customer accounts, checkout, order tracking, notifications, and support.",
      "It does not cover third-party websites or apps that we do not operate, even if they are linked from our services.",
    ],
  },
  {
    id: "information-you-provide",
    title: "2. Information you provide",
    paragraphs: [
      "Depending on how you use the platform, you may provide:",
    ],
    bullets: [
      "Account and profile details such as name, email address, phone number, and password credentials (stored in hashed form).",
      "Delivery and contact addresses used for fulfillment.",
      "Order, return, refund, and customer-support information you submit.",
      "Reviews or other content you choose to post about products where that feature is available.",
    ],
  },
  {
    id: "transaction-information",
    title: "3. Transaction and payment-related information",
    paragraphs: [
      "To operate commerce, we process order and fulfillment records, payment status, and payment-provider references or metadata returned by our payment orchestration and gateway integrations.",
      "CHINA ORDER TZ is designed so that raw payment-card details are handled by the payment gateway/provider rather than stored as card credentials in our application database. Gateway references and transaction status are retained as part of order and payment records.",
    ],
  },
  {
    id: "device-technical",
    title: "4. Device and technical information",
    paragraphs: [
      "When you use our apps or website, we may process technical and operational data such as:",
    ],
    bullets: [
      "Push-notification device or installation tokens, platform indicators, and related registration metadata used to deliver order and account alerts when you enable notifications.",
      "Storefront visitor/session identifiers and event information used for storefront analytics and service operation (for example page or product interaction events).",
      "Basic security and operational logs generated while providing the service.",
    ],
  },
  {
    id: "how-we-use",
    title: "5. How we use information",
    paragraphs: ["We use personal information to:"],
    bullets: [
      "Create and operate your customer account and authenticate access.",
      "Process checkout, payments status, fulfillment, shipping coordination, and order tracking.",
      "Provide customer support and respond to tickets or requests.",
      "Send transactional notifications (for example order, payment, shipment, or account-security related messages) by channels you use, such as in-app notifications, email, or push where configured.",
      "Protect accounts, prevent abuse, and maintain platform security and integrity.",
      "Improve storefront experience using operational analytics based on visitor/session/event data where those features are enabled.",
    ],
  },
  {
    id: "service-providers",
    title: "6. Service providers and processors",
    paragraphs: [
      "We use specialized providers to help run the platform. Categories may include hosting and infrastructure, transactional email delivery, push-notification delivery infrastructure, and payment gateway/processing partners.",
      "These providers process information only as needed to deliver their services to us. Exact contractual terms with each provider are operational matters and are not restated here.",
    ],
  },
  {
    id: "sharing",
    title: "7. Sharing",
    paragraphs: [
      "We share information with service providers as described above, and when required to fulfill an order (for example logistics or operational partners involved in delivery).",
      "We may disclose information if required by applicable law, regulation, legal process, or to protect the rights, safety, and integrity of customers, the platform, or others.",
      "We do not sell customer personal information as a product.",
    ],
  },
  {
    id: "cookies",
    title: "8. Cookies and similar technologies",
    paragraphs: [
      "Our website may use cookies, local storage, or similar technologies that are necessary for authentication, session continuity, cart/wishlist continuity, and storefront visitor/session identification used by our platform features.",
      "Where analytics or experience features rely on such identifiers, they are used to operate and improve the storefront. Browser controls may limit some cookies; essential functions may not work if required storage is blocked.",
      "This section summarizes cookie-related practices for the storefront. A separate dedicated cookie policy document is not published at this time.",
    ],
  },
  {
    id: "retention",
    title: "9. Retention",
    paragraphs: [
      "We retain personal information for as long as needed to provide the services and for related operational, security, accounting, dispute, and legal purposes.",
      "Order, payment, refund, and similar transactional records may need to be retained even after an account is closed or made inactive, where retention is necessary for legitimate business, accounting, audit, or legal reasons.",
      "This policy does not state fixed retention periods in days or years because those durations depend on operational and legal requirements that may change.",
    ],
  },
  {
    id: "account-requests",
    title: "10. Account and privacy requests",
    paragraphs: [
      "You can update certain profile and address information through your account settings where those features are available.",
      "Self-service in-app account deletion controls are being prepared for the platform. Until those controls are available in your account settings, you may contact support regarding privacy or account-closure requests.",
      "Depending on the request and applicable requirements, we may need to verify identity, retain transactional records as described above, and take reasonable time to process the request.",
    ],
  },
  {
    id: "security",
    title: "11. Security",
    paragraphs: [
      "We apply reasonable technical and organizational measures appropriate to our platform, including authenticated API access, hashed passwords, and operational controls.",
      "No method of transmission or storage is completely secure. Please protect your account credentials and notify us promptly of suspected unauthorized access.",
    ],
  },
  {
    id: "children",
    title: "12. Children",
    paragraphs: [
      "CHINA ORDER TZ is a commerce platform intended for customers who can form a binding purchase relationship. It is not directed at children.",
      "We do not knowingly collect personal information from children for the purpose of creating customer accounts. If you believe a child has provided personal information, contact us so we can review and take appropriate action.",
    ],
  },
  {
    id: "changes",
    title: "13. Changes to this policy",
    paragraphs: [
      "We may update this Privacy Policy from time to time. The effective date at the top of this page will change when we publish updates. Continued use of the services after an update means you should review the revised policy.",
    ],
  },
  {
    id: "contact",
    title: "14. Contact",
    paragraphs: [
      `For privacy or account-related requests, contact ${LEGAL_SUPPORT_EMAIL}.`,
    ],
  },
];
