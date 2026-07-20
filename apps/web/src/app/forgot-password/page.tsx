import { Suspense } from "react";
import { AuthSplitLayout } from "@/components/auth/AuthSplitLayout";
import { ForgotPasswordForm } from "@/components/auth/ForgotPasswordForm";

function ForgotFormFallback() {
  return (
    <div className="space-y-5" aria-hidden>
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-12 animate-pulse rounded-xl bg-[#c9a227]/30" />
    </div>
  );
}

export default function ForgotPasswordPage() {
  return (
    <AuthSplitLayout
      hero={{
        idPrefix: "forgot",
        eyebrow: "Account recovery",
        title: (
          <>
            We&apos;ve got you
            <span className="mt-1 block bg-gradient-to-r from-[#e8c547] to-[#c9a227] bg-clip-text text-transparent">
              covered.
            </span>
          </>
        ),
        subtitle: "Reset access in minutes so you can get back to shopping with confidence.",
      }}
      card={{
        eyebrow: "Password help",
        title: "Forgot your password?",
        description: "Enter your email and we'll send you instructions to reset it.",
      }}
    >
      <Suspense fallback={<ForgotFormFallback />}>
        <ForgotPasswordForm />
      </Suspense>
    </AuthSplitLayout>
  );
}
