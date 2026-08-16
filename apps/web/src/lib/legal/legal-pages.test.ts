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

describe("legal public pages foundation", () => {
  it("defines canonical privacy and terms paths", () => {
    assert.equal(LEGAL_PATHS.privacy, "/privacy");
    assert.equal(LEGAL_PATHS.terms, "/terms");
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

  it("does not claim an in-app delete-account button exists yet", () => {
    const accountSection = PRIVACY_SECTIONS.find((s) => s.id === "account-requests");
    assert.ok(accountSection);
    const text = accountSection!.paragraphs.join(" ");
    assert.match(text, /being prepared|contact support/i);
    assert.doesNotMatch(text, /Delete Account button/i);
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

  it("registers public Next.js routes for privacy and terms", () => {
    const privacyPage = readFileSync(
      join(process.cwd(), "src/app/(storefront)/privacy/page.tsx"),
      "utf8",
    );
    const termsPage = readFileSync(
      join(process.cwd(), "src/app/(storefront)/terms/page.tsx"),
      "utf8",
    );
    assert.match(privacyPage, /PRIVACY_PAGE_TITLE/);
    assert.match(termsPage, /TERMS_PAGE_TITLE/);
    assert.doesNotMatch(privacyPage, /auth|login required/i);
  });
});
