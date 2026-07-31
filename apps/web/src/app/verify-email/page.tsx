import { Suspense } from "react";
import { AuthSplitLayout } from "@/components/auth/AuthSplitLayout";
import { VerifyEmailForm } from "@/components/auth/VerifyEmailForm";

function VerifyFormFallback() {
  return (
    <div className="space-y-5" aria-hidden>
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-12 animate-pulse rounded-xl bg-[#c9a227]/30" />
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <AuthSplitLayout
      hero={{
        idPrefix: "verify",
        eyebrow: "Email confirmation",
        title: (
          <>
            Confirm your
            <span className="mt-1 block bg-gradient-to-r from-[#e8c547] to-[#c9a227] bg-clip-text text-transparent">
              email address.
            </span>
          </>
        ),
        subtitle: "A quick confirmation keeps your account secure and recoverable.",
      }}
      card={{
        eyebrow: "Verification",
        title: "Verify your email",
        description: "We are confirming the link from your inbox.",
      }}
    >
      <Suspense fallback={<VerifyFormFallback />}>
        <VerifyEmailForm />
      </Suspense>
    </AuthSplitLayout>
  );
}
