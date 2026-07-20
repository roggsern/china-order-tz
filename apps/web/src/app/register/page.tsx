import { Suspense } from "react";
import { AuthSplitLayout } from "@/components/auth/AuthSplitLayout";
import { RegisterForm } from "@/components/auth/RegisterForm";
import { RegisterMobileBenefits } from "@/components/auth/RegisterMobileBenefits";
import { REGISTER_BENEFITS } from "@/components/auth/register-benefits-data";

function RegisterFormFallback() {
  return (
    <div className="space-y-4" aria-hidden>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
        <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      </div>
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-12 animate-pulse rounded-xl bg-[#c9a227]/30" />
    </div>
  );
}

export default function RegisterPage() {
  return (
    <AuthSplitLayout
      hero={{
        idPrefix: "register",
        eyebrow: "Premium Global Commerce",
        title: (
          <>
            Welcome to
            <span className="mt-1 block bg-gradient-to-r from-[#e8c547] to-[#c9a227] bg-clip-text text-transparent">
              China Order TZ
            </span>
          </>
        ),
        subtitle:
          "Create an account to unlock wholesale pricing, secure checkout, and trusted shipping from China to East Africa.",
        trustItems: [
          { label: "Secure Checkout" },
          { label: "Wholesale Pricing" },
          { label: "Trusted Suppliers" },
          { label: "Fast Customer Support" },
        ],
        children: (
          <ul className="grid gap-3 sm:grid-cols-2">
            {REGISTER_BENEFITS.map((benefit) => (
              <li
                key={benefit.title}
                className="group rounded-2xl border border-white/10 bg-white/[0.04] p-3.5 backdrop-blur-md transition duration-300 ease-out hover:-translate-y-0.5 hover:border-[#c9a227]/30 hover:bg-white/[0.07]"
              >
                <div className="flex items-start gap-3">
                  <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#c9a227]/25 bg-[#c9a227]/10 text-[#e8c547]">
                    {benefit.icon}
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-white">{benefit.title}</p>
                    <p className="mt-0.5 text-xs leading-relaxed text-zinc-400">
                      {benefit.description}
                    </p>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        ),
      }}
      card={{
        eyebrow: "Get started",
        title: "Create your account",
        description:
          "Join thousands of shoppers importing smarter — it only takes a minute.",
      }}
      belowCard={<RegisterMobileBenefits />}
    >
      <Suspense fallback={<RegisterFormFallback />}>
        <RegisterForm />
      </Suspense>
    </AuthSplitLayout>
  );
}
