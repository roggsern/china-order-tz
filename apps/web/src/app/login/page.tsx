import Link from "next/link";
import { AnimatedLogo } from "@/components/branding/AnimatedLogo";

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

          <form className="mt-8 space-y-5" action="#" method="post">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-zinc-300">
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                className="mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder="you@example.com"
              />
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-zinc-300">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                className="mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder="••••••••"
              />
            </div>

            <button
              type="submit"
              className="w-full rounded-xl bg-[#c9a227] py-3 text-sm font-bold uppercase tracking-wide text-zinc-900 shadow-lg shadow-[#c9a227]/20 transition hover:bg-[#e8c547]"
            >
              Sign in
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-zinc-500">
            Don&apos;t have an account?{" "}
            <Link href="#" className="font-medium text-[#e8c547] hover:text-[#c9a227]">
              Create account
            </Link>
          </p>
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
