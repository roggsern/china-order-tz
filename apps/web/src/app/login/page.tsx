import Link from "next/link";
import { AnimatedLogo } from "@/components/branding/AnimatedLogo";
import { LoginForm } from "@/components/auth/LoginForm";

export default function LoginPage() {
  return (
    <div className="flex min-h-screen flex-col bg-zinc-950">
      <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-12 sm:px-6">
        <div className="mb-8">
          <AnimatedLogo className="mx-auto max-w-sm" />
        </div>

        <div className="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 shadow-2xl shadow-black/40 backdrop-blur sm:p-8">
          <h1 className="text-center text-xl font-bold text-white">Welcome back</h1>
          <p className="mt-2 text-center text-sm text-zinc-400">
            Sign in to your CHINA ORDER TZ account
          </p>

          <LoginForm />
        </div>

        <p className="mt-8 text-center">
          <Link href="/" className="text-sm text-zinc-500 transition hover:text-zinc-300">
            ← Back to storefront
          </Link>
        </p>
      </div>
    </div>
  );
}
