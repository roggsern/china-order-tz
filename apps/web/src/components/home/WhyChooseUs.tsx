import { whyChooseUs } from "@/lib/home-data";
import { ShieldIcon, ShippingIcon, SupportIcon, TagIcon } from "./icons";

const iconMap = {
  shipping: ShippingIcon,
  shield: ShieldIcon,
  tag: TagIcon,
  support: SupportIcon,
} as const;

export function WhyChooseUs() {
  return (
    <section id="about" className="relative overflow-hidden bg-white py-20 sm:py-28">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-[#c9a227]/30 to-transparent" />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
            The CHINA ORDER TZ Difference
          </p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl lg:text-[2.75rem]">
            Why Choose Us
          </h2>
          <p className="mt-4 text-base leading-relaxed text-zinc-500">
            A premium import experience built for Tanzanian shoppers and businesses — from sourcing
            to doorstep delivery.
          </p>
        </div>

        <div className="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {whyChooseUs.map((item, index) => {
            const Icon = iconMap[item.icon];
            return (
              <div
                key={item.title}
                className="premium-card group relative overflow-hidden rounded-3xl border border-zinc-100 bg-zinc-50 p-7 shadow-[0_4px_24px_rgba(0,0,0,0.04)] transition duration-500 hover:-translate-y-1 hover:border-[#c9a227]/30 hover:bg-white hover:shadow-[0_16px_40px_rgba(0,0,0,0.08)]"
              >
                <span className="absolute right-5 top-5 text-5xl font-bold text-zinc-100 transition group-hover:text-[#c9a227]/10">
                  {String(index + 1).padStart(2, "0")}
                </span>
                <div className="relative">
                  <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-900 text-[#c9a227] shadow-lg shadow-zinc-900/20 transition duration-500 group-hover:bg-[#c9a227] group-hover:text-zinc-900 group-hover:shadow-[#c9a227]/25">
                    <Icon className="h-6 w-6" />
                  </div>
                  <h3 className="mt-6 text-lg font-bold text-zinc-900">{item.title}</h3>
                  <p className="mt-2.5 text-sm leading-relaxed text-zinc-500">{item.description}</p>
                </div>
                <div className="absolute bottom-0 left-0 right-0 h-0.5 scale-x-0 bg-gradient-to-r from-[#c9a227] to-[#e8c547] transition duration-500 group-hover:scale-x-100" />
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
