import type { Metadata } from "next";
import { LegalDocumentPage } from "@/components/legal/LegalDocumentPage";
import {
  PRIVACY_INTRO,
  PRIVACY_PAGE_DESCRIPTION,
  PRIVACY_PAGE_TITLE,
  PRIVACY_SECTIONS,
} from "@/lib/legal/privacy-content";

export const metadata: Metadata = {
  title: PRIVACY_PAGE_TITLE,
  description: PRIVACY_PAGE_DESCRIPTION,
  openGraph: {
    title: PRIVACY_PAGE_TITLE,
    description: PRIVACY_PAGE_DESCRIPTION,
  },
};

export default function PrivacyPolicyPage() {
  return (
    <LegalDocumentPage
      title="Privacy Policy"
      intro={PRIVACY_INTRO}
      sections={PRIVACY_SECTIONS}
    />
  );
}
