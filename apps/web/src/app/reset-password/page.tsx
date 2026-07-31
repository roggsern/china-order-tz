import { Suspense } from "react";
import { AuthSplitLayout } from "@/components/auth/AuthSplitLayout";
import { ResetPasswordForm } from "@/components/auth/ResetPasswordForm";

function ResetFormFallback() {
  return (
    <div className="space-y-5" aria-hidden>
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-12 animate-pulse rounded-xl bg-[#c9a227]/30" />
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <AuthSplitLayout
      hero={{
        idPrefix: "reset",
        eyebrow: "Account recovery",
        title: (
          <>
            Choose a
            <span className="mt-1 block bg-gradient-to-r from-[#e8c547] to-[#c9a227] bg-clip-text text-transparent">
              new password.
            </span>
          </>
        ),
        subtitle: "Set a strong password so your account stays protected.",
      }}
      card={{
        eyebrow: "Password reset",
        title: "Create a new password",
        description: "Enter and confirm your new password to finish recovering your account.",
      }}
    >
      <Suspense fallback={<ResetFormFallback />}>
        <ResetPasswordForm />
      </Suspense>
    </AuthSplitLayout>
  );
}
