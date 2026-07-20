import { REGISTER_BENEFITS } from "@/components/auth/register-benefits-data";

export function RegisterMobileBenefits() {
  return (
    <section
      aria-label="Why shop with CHINA ORDER TZ"
      className="mt-6 rounded-2xl border border-zinc-800/80 bg-zinc-900/50 p-4 backdrop-blur sm:p-5 lg:hidden"
    >
      <p className="text-center text-[0.65rem] font-bold uppercase tracking-[0.22em] text-[#e8c547]">
        Why CHINA ORDER TZ
      </p>
      <ul className="mt-4 grid gap-3 sm:grid-cols-2">
        {REGISTER_BENEFITS.map((benefit) => (
          <li
            key={benefit.title}
            className="rounded-xl border border-white/10 bg-white/[0.03] p-3"
          >
            <div className="flex items-start gap-2.5">
              <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#c9a227]/20 bg-[#c9a227]/10 text-[#e8c547]">
                {benefit.icon}
              </span>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-white">{benefit.title}</p>
                <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{benefit.description}</p>
              </div>
            </div>
          </li>
        ))}
      </ul>
      <p className="mt-4 text-center text-xs text-zinc-500">
        Trusted by shoppers across Tanzania and East Africa.
      </p>
    </section>
  );
}
