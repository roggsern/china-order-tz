"use client";

import { useState } from "react";
import Link from "next/link";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { useAdminAuth } from "@/components/admin/AdminAuthProvider";
import { DEFAULT_ADMIN_EMAIL } from "@/lib/admin/credentials";

export function AdminLoginContent() {
  const { signIn } = useAdminAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(undefined);

    const trimmedEmail = email.trim();
    if (!trimmedEmail) {
      setError("Please enter your admin email address.");
      return;
    }

    if (!password) {
      setError("Please enter your password.");
      return;
    }

    setIsSubmitting(true);

    try {
      const result = await signIn(trimmedEmail, password);
      if (!result.ok) {
        setError(
          result.message ||
            "Invalid email or password. Please check your credentials and try again.",
        );
      }
    } catch {
      setError("Unable to reach the authentication server. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="relative flex min-h-screen flex-col overflow-hidden bg-zinc-950">
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(201,162,39,0.14)_0%,_transparent_55%)]"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#c9a227]/40 to-transparent"
        aria-hidden
      />

      <div className="relative mx-auto flex w-full max-w-[26rem] flex-1 flex-col justify-center px-4 py-10 sm:px-6 sm:py-14">
        <div className="mb-8 flex flex-col items-center text-center sm:mb-10">
          <div className="rounded-2xl border border-zinc-800/90 bg-zinc-900/70 px-5 py-4 shadow-[0_20px_60px_rgba(0,0,0,0.35)] ring-1 ring-[#c9a227]/10">
            <HorizontalBrandLogo size="sm" href="/" height={44} />
          </div>
          <p className="mt-5 text-[11px] font-bold uppercase tracking-[0.22em] text-[#c9a227]">
            Internal Operations Portal
          </p>
          <h1 className="mt-2 text-lg font-semibold text-white sm:text-xl">CHINA ORDER TZ Admin</h1>
          <p className="mt-2 max-w-xs text-sm leading-relaxed text-zinc-400">
            Secure access for store operations, fulfilment, and catalog management.
          </p>
        </div>

        <div className="rounded-2xl border border-zinc-800 bg-zinc-900/95 p-6 shadow-2xl shadow-black/50 ring-1 ring-white/5 sm:p-8">
          <div className="border-b border-zinc-800 pb-5 text-center">
            <h2 className="text-lg font-bold text-white">Sign in</h2>
            <p className="mt-1.5 text-sm text-zinc-400">
              Use your admin credentials. Separate from customer accounts.
            </p>
          </div>

          <form className="mt-6 space-y-5" onSubmit={handleSubmit} noValidate>
            <div>
              <label htmlFor="admin-email" className="admin-label text-zinc-300">
                Email
              </label>
              <input
                id="admin-email"
                name="email"
                type="email"
                autoComplete="username"
                required
                value={email}
                onChange={(event) => {
                  setEmail(event.target.value);
                  if (error) setError(undefined);
                }}
                className="admin-touch-input mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder={DEFAULT_ADMIN_EMAIL}
              />
            </div>

            <div>
              <label htmlFor="admin-password" className="admin-label text-zinc-300">
                Password
              </label>
              <input
                id="admin-password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(event) => {
                  setPassword(event.target.value);
                  if (error) setError(undefined);
                }}
                className="admin-touch-input mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder="Enter password"
              />
            </div>

            {error ? (
              <div
                role="alert"
                className="rounded-xl border border-red-500/40 bg-red-950/60 px-4 py-3 text-sm text-red-200"
              >
                <p className="font-semibold text-red-100">Sign in failed</p>
                <p className="mt-1">{error}</p>
              </div>
            ) : null}

            <button
              type="submit"
              disabled={isSubmitting}
              className="admin-touch-target w-full rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-4 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/20 transition hover:from-[#b8921f] hover:to-[#d4b83d] disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSubmitting ? "Signing in…" : "Sign in to dashboard"}
            </button>
          </form>

          <p className="mt-6 rounded-xl border border-zinc-800 bg-zinc-950/60 px-4 py-3 text-center text-xs text-zinc-500">
            Local Laravel admin:{" "}
            <span className="font-mono text-zinc-400">{DEFAULT_ADMIN_EMAIL}</span>
            {" / "}
            <span className="font-mono text-zinc-400">password</span>
          </p>
        </div>

        <p className="mt-8 text-center">
          <Link href="/" className="text-sm text-zinc-500 transition hover:text-[#c9a227]">
            ← Back to storefront
          </Link>
        </p>
      </div>
    </div>
  );
}
