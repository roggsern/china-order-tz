import Link from "next/link";
import { OfficialLogoImage } from "@/components/branding/OfficialLogoImage";
import {
  LEGAL_BRAND_NAME,
  LEGAL_EFFECTIVE_DATE_LABEL,
  LEGAL_SUPPORT_EMAIL,
  LEGAL_SUPPORT_MAILTO,
} from "@/lib/legal/legal-paths";

export type LegalSection = {
  id: string;
  title: string;
  paragraphs: string[];
  bullets?: string[];
};

type LegalDocumentPageProps = {
  title: string;
  intro: string;
  sections: LegalSection[];
};

export function LegalDocumentPage({ title, intro, sections }: LegalDocumentPageProps) {
  return (
    <div className="bg-[#faf8f3]">
      <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <div className="mb-8 flex items-center gap-3">
          <OfficialLogoImage variant="favicon" height={40} className="h-10 w-10" />
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#c9a227]">
              {LEGAL_BRAND_NAME}
            </p>
            <p className="text-sm text-zinc-500">Effective {LEGAL_EFFECTIVE_DATE_LABEL}</p>
          </div>
        </div>

        <h1 className="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
          {title}
        </h1>
        <p className="mt-4 text-base leading-relaxed text-zinc-600">{intro}</p>

        <nav
          aria-label="On this page"
          className="mt-8 rounded-2xl border border-zinc-200/80 bg-white/80 p-4"
        >
          <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
            On this page
          </p>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {sections.map((section) => (
              <li key={section.id}>
                <a
                  href={`#${section.id}`}
                  className="text-sm text-zinc-700 underline-offset-2 hover:text-[#c9a227] hover:underline"
                >
                  {section.title}
                </a>
              </li>
            ))}
          </ul>
        </nav>

        <div className="mt-10 space-y-10">
          {sections.map((section) => (
            <section key={section.id} id={section.id} className="scroll-mt-24">
              <h2 className="text-xl font-semibold text-zinc-900">{section.title}</h2>
              <div className="mt-3 space-y-3 text-base leading-relaxed text-zinc-600">
                {section.paragraphs.map((paragraph, index) => (
                  <p key={`${section.id}-p-${index}`}>{paragraph}</p>
                ))}
                {section.bullets && section.bullets.length > 0 ? (
                  <ul className="list-disc space-y-2 pl-5">
                    {section.bullets.map((item, index) => (
                      <li key={`${section.id}-b-${index}`}>{item}</li>
                    ))}
                  </ul>
                ) : null}
              </div>
            </section>
          ))}
        </div>

        <div className="mt-12 rounded-2xl border border-zinc-200 bg-white p-5 text-sm text-zinc-600">
          <p>
            Questions about this document? Contact{" "}
            <a
              href={LEGAL_SUPPORT_MAILTO}
              className="font-medium text-[#c9a227] underline-offset-2 hover:underline"
            >
              {LEGAL_SUPPORT_EMAIL}
            </a>
            .
          </p>
          <p className="mt-3">
            <Link href="/" className="text-zinc-800 underline-offset-2 hover:underline">
              Return to storefront
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
