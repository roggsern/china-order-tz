import type { Metadata } from "next";
import { LegalDocumentPage } from "@/components/legal/LegalDocumentPage";
import {
  DELETE_ACCOUNT_INTRO,
  DELETE_ACCOUNT_PAGE_DESCRIPTION,
  DELETE_ACCOUNT_PAGE_TITLE,
  DELETE_ACCOUNT_SECTIONS,
} from "@/lib/legal/delete-account-content";

export const metadata: Metadata = {
  title: DELETE_ACCOUNT_PAGE_TITLE,
  description: DELETE_ACCOUNT_PAGE_DESCRIPTION,
  openGraph: {
    title: DELETE_ACCOUNT_PAGE_TITLE,
    description: DELETE_ACCOUNT_PAGE_DESCRIPTION,
  },
};

export default function DeleteAccountInstructionsPage() {
  return (
    <LegalDocumentPage
      title="Delete / close account"
      intro={DELETE_ACCOUNT_INTRO}
      sections={DELETE_ACCOUNT_SECTIONS}
    />
  );
}
