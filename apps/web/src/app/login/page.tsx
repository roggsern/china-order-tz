import { Suspense } from "react";
import { AuthSplitLayout } from "@/components/auth/AuthSplitLayout";
import { LoginForm } from "@/components/auth/LoginForm";

function LoginFormFallback() {
  return (
    <div className="space-y-5" aria-hidden>
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-11 animate-pulse rounded-2xl bg-zinc-800/80" />
      <div className="h-12 animate-pulse rounded-xl bg-[#c9a227]/30" />
    </div>
  );
}

export default function LoginPage() {
  return (
    <AuthSplitLayout
      hero={{
        idPrefix: "login",
        eyebrow: "China Order TZ",
        title: (
          <>
            Import Smarter.
            <span className="mt-1 block bg-gradient-to-r from-[#e8c547] to-[#c9a227] bg-clip-text text-transparent">
              Shop Better.
            </span>
          </>
        ),
        subtitle:
          "Sign in to checkout securely, unlock wholesale pricing, and track every order from China to your door.",
      }}
      card={{
        eyebrow: "Welcome back",
        title: "Sign in to your account",
        description: "Access your cart, orders, and saved preferences.",
      }}
    >
      <Suspense fallback={<LoginFormFallback />}>
        <LoginForm />
      </Suspense>
    </AuthSplitLayout>
  );
}
