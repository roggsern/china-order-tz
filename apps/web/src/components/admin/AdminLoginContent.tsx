"use client";

import { useState } from "react";
import Link from "next/link";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { useAdminAuth } from "@/components/admin/AdminAuthProvider";

export function AdminLoginContent() {
  const { signIn } = useAdminAuth();
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | undefined>();

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    setError(undefined);

    if (!signIn(pin)) {
      setError("Invalid admin credentials. Please try again.");
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
            Manage orders, products, and store settings. Customer storefront data stays separate.
          </p>

          <form className="mt-8 space-y-5" onSubmit={handleSubmit}>
            <div>
              <label htmlFor="admin-pin" className="block text-sm font-medium text-zinc-300">
                Admin PIN
              </label>
              <input
                id="admin-pin"
                name="pin"
                type="password"
                autoComplete="current-password"
                required
                value={pin}
                onChange={(event) => setPin(event.target.value)}
                className="mt-1.5 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder:text-zinc-500 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20"
                placeholder="Enter admin PIN"
              />
            </div>

            {error ? (
              <p role="alert" className="rounded-xl border border-red-500/30 bg-red-950/50 px-4 py-3 text-sm text-red-300">
                {error}
              </p>
            ) : null}

            <button
              type="submit"
              className="w-full rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/20 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
            >
              Sign in to dashboard
            </button>
          </form>
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
