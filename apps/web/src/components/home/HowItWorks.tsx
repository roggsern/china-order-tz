import { howItWorksSteps, supportedPlatforms } from "@/lib/home-data";
import { DocumentIcon, LinkIcon, TrackIcon } from "./icons";

const stepIconMap = {
  link: LinkIcon,
  quote: DocumentIcon,
  track: TrackIcon,
} as const;

export function HowItWorks() {
  return (
    <section id="how-it-works" className="relative overflow-hidden bg-white py-20 sm:py-24">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -right-24 top-0 h-72 w-72 rounded-full bg-[#c9a227]/5 blur-3xl" />
        <div className="absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-zinc-100 blur-3xl" />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Simple Process
          </p>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            How It Works
          </h2>
          <p className="mt-3 text-base text-zinc-500">
            Import any product from China in three easy steps — no sourcing experience required.
          </p>
          <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
            {supportedPlatforms.map((platform) => (
              <span
                key={platform}
                className="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold text-zinc-600"
              >
                {platform}
              </span>
            ))}
          </div>
        </div>

        <div className="relative mt-16">
          <div
            className="absolute left-0 right-0 top-[3.25rem] hidden h-px bg-gradient-to-r from-transparent via-[#c9a227]/40 to-transparent lg:block"
            aria-hidden
          />

          <ol className="grid gap-8 lg:grid-cols-3 lg:gap-6">
            {howItWorksSteps.map((step) => {
              const Icon = stepIconMap[step.icon];
              return (
                <li
                  key={step.step}
                  className="group relative flex flex-col items-center text-center lg:items-start lg:text-left"
                >
                  <div className="relative">
                    <div className="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-2xl border border-zinc-200 bg-white shadow-lg shadow-zinc-900/5 transition group-hover:border-[#c9a227]/40 group-hover:shadow-[#c9a227]/10">
                      <Icon className="h-7 w-7 text-[#c9a227]" />
                    </div>
                    <span className="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-[#e8c547]">
                      {step.step}
                    </span>
                  </div>

                  <h3 className="mt-6 text-xl font-bold text-zinc-900">{step.title}</h3>
                  <p className="mt-2 max-w-xs text-sm leading-relaxed text-zinc-500">
                    {step.description}
                  </p>
                  <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-[#c9a227]">
                    {step.detail}
                  </p>
                </li>
              );
            })}
          </ol>
        </div>
      </div>
    </section>
  );
}
