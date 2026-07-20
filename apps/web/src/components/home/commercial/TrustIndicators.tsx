import type { HomepageSectionCopy, HomepageTrustIndicator } from "@/lib/content/homepage";

const ICON_GLYPH: Record<HomepageTrustIndicator["icon"], string> = {
  secure: "🔒",
  delivery: "🚚",
  support: "📞",
  returns: "↩️",
  official: "🏷️",
  quality: "⭐",
};

type TrustIndicatorsProps = {
  items: HomepageTrustIndicator[];
  copy: HomepageSectionCopy;
};

export function TrustIndicators({ items, copy }: TrustIndicatorsProps) {
  return (
    <section id="trust" className="border-y border-zinc-100 bg-white py-12 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
            {copy.eyebrow}
          </p>
          <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
            {copy.title}
          </h2>
          <p className="mt-2 text-sm text-zinc-500">{copy.description}</p>
        </div>

        <ul className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {items.map((item) => (
            <li
              key={item.id}
              className="rounded-2xl border border-zinc-100 bg-zinc-50/80 px-3 py-4 text-center"
            >
              <span className="text-xl" aria-hidden>
                {ICON_GLYPH[item.icon]}
              </span>
              <h3 className="mt-2 text-xs font-bold text-zinc-900 sm:text-sm">{item.title}</h3>
              <p className="mt-1 text-[11px] leading-relaxed text-zinc-500 sm:text-xs">
                {item.description}
              </p>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
