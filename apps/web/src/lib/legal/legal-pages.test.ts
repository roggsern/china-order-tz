import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import {
  LEGAL_PATHS,
  LEGAL_SUPPORT_EMAIL,
} from "./legal-paths";
import {
  PRIVACY_PAGE_DESCRIPTION,
  PRIVACY_PAGE_TITLE,
  PRIVACY_SECTIONS,
} from "./privacy-content";
import {
  TERMS_PAGE_DESCRIPTION,
  TERMS_PAGE_TITLE,
  TERMS_SECTIONS,
} from "./terms-content";
import {
  DELETE_ACCOUNT_PAGE_TITLE,
  DELETE_ACCOUNT_SECTIONS,
} from "./delete-account-content";

describe("legal public pages foundation", () => {
  it("defines canonical privacy, terms, and delete-account paths", () => {
    assert.equal(LEGAL_PATHS.privacy, "/privacy");
    assert.equal(LEGAL_PATHS.terms, "/terms");
    assert.equal(LEGAL_PATHS.deleteAccount, "/delete-account");
    assert.equal(LEGAL_PATHS.cookiesAnchor, "/privacy#cookies");
  });

  it("uses the authoritative support contact email", () => {
    assert.equal(LEGAL_SUPPORT_EMAIL, "support@chinaordertz.com");
  });

  it("exposes privacy metadata and required section coverage", () => {
    assert.match(PRIVACY_PAGE_TITLE, /Privacy Policy/);
    assert.ok(PRIVACY_PAGE_DESCRIPTION.length > 20);
    const ids = PRIVACY_SECTIONS.map((section) => section.id);
    assert.ok(ids.includes("information-you-provide"));
    assert.ok(ids.includes("transaction-information"));
    assert.ok(ids.includes("device-technical"));
    assert.ok(ids.includes("cookies"));
    assert.ok(ids.includes("account-requests"));
    assert.ok(ids.includes("retention"));
  });

  it("documents live self-service account closure without false delete-button claims", () => {
    const accountSection = PRIVACY_SECTIONS.find((s) => s.id === "account-requests");
    assert.ok(accountSection);
    const text = accountSection!.paragraphs.join(" ");
    assert.match(text, /Close account/i);
    assert.doesNotMatch(text, /being prepared/i);
    assert.doesNotMatch(text, /Delete Account button/i);
    assert.match(text, /\/delete-account/);
  });

  it("exposes public delete-account instructions without unauthenticated deletion", () => {
    assert.match(DELETE_ACCOUNT_PAGE_TITLE, /Delete account/i);
    const blob = DELETE_ACCOUNT_SECTIONS.flatMap((s) => [
      ...s.paragraphs,
      ...(s.bullets ?? []),
    ]).join("\n");
    assert.match(blob, /signed in/i);
    assert.match(blob, /current password/i);
    assert.doesNotMatch(blob, /enter your email to delete/i);
  });

  it("exposes terms metadata and journey coverage", () => {
    assert.match(TERMS_PAGE_TITLE, /Terms of Service/);
    assert.ok(TERMS_PAGE_DESCRIPTION.length > 20);
    const joined = TERMS_SECTIONS.map((s) => s.paragraphs.join(" ") + (s.bullets ?? []).join(" ")).join(" ");
    assert.match(joined, /CHINA_IMPORT|Order from China/);
    assert.match(joined, /TZ_LOCAL|Buy from Tanzania/);
  });

  it("avoids unsupported absolute legal certifications in policy copy", () => {
    const blob = [
      ...PRIVACY_SECTIONS.flatMap((s) => [...s.paragraphs, ...(s.bullets ?? [])]),
      ...TERMS_SECTIONS.flatMap((s) => [...s.paragraphs, ...(s.bullets ?? [])]),
      ...DELETE_ACCOUNT_SECTIONS.flatMap((s) => [...s.paragraphs, ...(s.bullets ?? [])]),
    ].join("\n");

    assert.doesNotMatch(blob, /\bGDPR compliant\b/i);
    assert.doesNotMatch(blob, /\bPDPA compliant\b/i);
    assert.doesNotMatch(blob, /\bnever share\b/i);
    assert.doesNotMatch(blob, /\bnever store\b/i);
    assert.doesNotMatch(blob, /\bpermanently delete\b/i);
    assert.doesNotMatch(blob, /\bretained for \d+ (days|years)\b/i);
  });

  it("wires footer Privacy and Terms to canonical routes", () => {
    const footerSource = readFileSync(
      join(process.cwd(), "src/components/home/Footer.tsx"),
      "utf8",
    );
    assert.match(footerSource, /href="\/privacy"/);
    assert.match(footerSource, /href="\/terms"/);
    assert.match(footerSource, /href="\/privacy#cookies"/);
    assert.doesNotMatch(
      footerSource,
      /Privacy Policy[\s\S]{0,80}href="#"/,
    );
  });

  it("registers public Next.js routes for privacy, terms, and delete-account", () => {
    const privacyPage = readFileSync(
      join(process.cwd(), "src/app/(storefront)/privacy/page.tsx"),
      "utf8",
    );
    const termsPage = readFileSync(
      join(process.cwd(), "src/app/(storefront)/terms/page.tsx"),
      "utf8",
    );
    const deleteAccountPage = readFileSync(
      join(process.cwd(), "src/app/(storefront)/delete-account/page.tsx"),
      "utf8",
    );
    assert.match(privacyPage, /PRIVACY_PAGE_TITLE/);
    assert.match(termsPage, /TERMS_PAGE_TITLE/);
    assert.match(deleteAccountPage, /DELETE_ACCOUNT_PAGE_TITLE/);
    assert.doesNotMatch(privacyPage, /auth|login required/i);
    assert.doesNotMatch(deleteAccountPage, /getCustomerApiToken|auth:sanctum/i);
  });

  it("wires authenticated web close-account UI to the shared API path", () => {
    const security = readFileSync(
      join(process.cwd(), "src/components/account/AccountSecurityContent.tsx"),
      "utf8",
    );
    const bff = readFileSync(
      join(process.cwd(), "src/app/api/account/close/route.ts"),
      "utf8",
    );
    assert.match(security, /closeCustomerAccount/);
    assert.match(security, /Close account/);
    assert.match(bff, /\/account\/close/);
  });
});
