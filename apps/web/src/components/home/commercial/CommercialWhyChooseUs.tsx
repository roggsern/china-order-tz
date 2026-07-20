import type { HomepageSectionCopy, HomepageWhyChooseItem } from "@/lib/content/homepage";
import { LockIcon, ShieldIcon, ShippingIcon, SupportIcon } from "../icons";

const ICON_MAP = {
  import: ShieldIcon,
  delivery: ShippingIcon,
  secure: LockIcon,
  quality: ShieldIcon,
  support: SupportIcon,
} as const;

type CommercialWhyChooseUsProps = {
  items: HomepageWhyChooseItem[];
  copy: HomepageSectionCopy;
};

export function CommercialWhyChooseUs({ items, copy }: CommercialWhyChooseUsProps) {
  return (
    <section id="why-choose-us" className="bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
            {copy.eyebrow}
          </p>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            {copy.title}
          </h2>
          <p className="mt-3 text-sm leading-relaxed text-zinc-500 sm:text-base">
            {copy.description}
          </p>
        </div>

        <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {items.map((item) => {
            const Icon = ICON_MAP[item.icon];
            return (
              <li
                key={item.id}
                className="rounded-2xl border border-zinc-100 bg-zinc-50 p-5 transition hover:border-[#c9a227]/30 hover:bg-white hover:shadow-[0_8px_24px_rgba(0,0,0,0.05)]"
              >
                <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-zinc-900 text-[#c9a227]">
                  <Icon className="h-5 w-5" />
                </span>
                <h3 className="mt-4 text-base font-bold text-zinc-900">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-zinc-500">{item.description}</p>
              </li>
            );
          })}
        </ul>
      </div>
    </section>
  );
}
