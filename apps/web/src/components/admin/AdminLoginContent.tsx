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

  const handleSubmit = (event: React.FormEvent) => {
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

    const ok = signIn(trimmedEmail, password);
    if (!ok) {
      setError("Invalid email or password. Please check your credentials and try again.");
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col bg-zinc-950">
      <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-12 sm:px-6">
        <div className="mb-8 flex flex-col items-center gap-3">
          <HorizontalBrandLogo size="sm" />
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
            Admin Portal
          </p>
        </div>

        <div className="rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 shadow-2xl shadow-black/40 sm:p-8">
          <h1 className="text-center text-xl font-bold text-white">Admin sign in</h1>
          <p className="mt-2 text-center text-sm text-zinc-400">
            Manage orders, products, and store settings. Separate from customer accounts.
          </p>

          <form className="mt-8 space-y-5" onSubmit={handleSubmit} noValidate>
            <div>
              <label htmlFor="admin-email" className="block text-sm font-medium text-zinc-300">
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
                className="mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder={DEFAULT_ADMIN_EMAIL}
              />
            </div>

            <div>
              <label htmlFor="admin-password" className="block text-sm font-medium text-zinc-300">
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
                className="mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
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
              className="w-full rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/20 transition hover:from-[#b8921f] hover:to-[#d4b83d] disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSubmitting ? "Signing in…" : "Sign in to dashboard"}
            </button>
          </form>

          <p className="mt-6 rounded-xl border border-zinc-800 bg-zinc-950/60 px-4 py-3 text-center text-xs text-zinc-500">
            Default local credentials:{" "}
            <span className="font-mono text-zinc-400">{DEFAULT_ADMIN_EMAIL}</span>
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
