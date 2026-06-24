import { whyChooseUs } from "@/lib/home-data";
import { LockIcon, ShieldIcon, ShippingIcon, TagIcon } from "./icons";

const iconMap = {
  shipping: ShippingIcon,
  shield: ShieldIcon,
  tag: TagIcon,
  lock: LockIcon,
} as const;

export function WhyChooseUs() {
  return (
    <section id="about" className="border-y border-zinc-100 bg-zinc-50 py-20 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Why CHINA ORDER TZ
          </p>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Why Choose Us
          </h2>
          <p className="mt-3 text-base text-zinc-500">
            We combine Alibaba&apos;s sourcing power with Temu&apos;s value and Apple-level
            service — built for Tanzanian shoppers and businesses.
          </p>
        </div>

        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {whyChooseUs.map((item) => {
            const Icon = iconMap[item.icon];
            return (
              <div
                key={item.title}
                className="group rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm transition hover:border-[#c9a227]/30 hover:shadow-lg"
              >
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-900 text-[#c9a227] transition group-hover:bg-[#c9a227] group-hover:text-zinc-900">
                  <Icon className="h-6 w-6" />
                </div>
                <h3 className="mt-5 text-lg font-bold text-zinc-900">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-zinc-500">{item.description}</p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
