import type { Metadata } from "next";
import { LegalDocumentPage } from "@/components/legal/LegalDocumentPage";
import {
  TERMS_INTRO,
  TERMS_PAGE_DESCRIPTION,
  TERMS_PAGE_TITLE,
  TERMS_SECTIONS,
} from "@/lib/legal/terms-content";

export const metadata: Metadata = {
  title: TERMS_PAGE_TITLE,
  description: TERMS_PAGE_DESCRIPTION,
  openGraph: {
    title: TERMS_PAGE_TITLE,
    description: TERMS_PAGE_DESCRIPTION,
  },
};

export default function TermsOfServicePage() {
  return (
    <LegalDocumentPage
      title="Terms of Service"
      intro={TERMS_INTRO}
      sections={TERMS_SECTIONS}
    />
  );
}
