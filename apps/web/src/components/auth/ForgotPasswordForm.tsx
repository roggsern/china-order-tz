"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
  AUTH_INPUT_CLASS,
  AUTH_LABEL_CLASS,
  AUTH_PRIMARY_BUTTON_CLASS,
  AUTH_SECONDARY_LINK_CLASS,
} from "@/components/auth/auth-styles";
import { AuthLoadingSpinner } from "@/components/auth/AuthLoadingSpinner";
import { withPreservedReturnUrl } from "@/lib/auth/return-url";

/**
 * Frontend-only forgot-password UX.
 * Does not call backend password-reset APIs (none exposed for customers yet).
 */
export function ForgotPasswordForm() {
  const searchParams = useSearchParams();
  const returnUrl = searchParams.get("returnUrl");
  const [email, setEmail] = useState("");
  const [error, setError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(undefined);

    const nextEmail = email.trim();
    if (!nextEmail) {
      setError("Please enter your email address.");
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(nextEmail)) {
      setError("Please enter a valid email address.");
      return;
    }

    setIsSubmitting(true);
    // Soft UX delay — no backend password-reset endpoint for customers yet.
    await new Promise((resolve) => setTimeout(resolve, 650));
    setIsSubmitting(false);
    setSubmitted(true);
  };

  if (submitted) {
    return (
      <div className="space-y-5 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
          <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <div>
          <h2 className="text-lg font-bold text-white">Check your inbox</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">
            If an account exists for <span className="font-medium text-zinc-200">{email.trim()}</span>,
            you&apos;ll receive instructions to reset your password shortly.
          </p>
          <p className="mt-3 text-xs leading-relaxed text-zinc-500">
            Didn&apos;t get an email? Check spam, or contact support and we&apos;ll help you get back
            in.
          </p>
        </div>
        <Link
          href={withPreservedReturnUrl("/login", returnUrl)}
          className={AUTH_PRIMARY_BUTTON_CLASS}
        >
          Back to sign in
        </Link>
      </div>
    );
  }

  return (
    <>
      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        <div>
          <label htmlFor="email" className={AUTH_LABEL_CLASS}>
            Email
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            disabled={isSubmitting}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className={AUTH_INPUT_CLASS}
            placeholder="you@example.com"
          />
        </div>

        {error ? (
          <p
            role="alert"
            className="rounded-2xl border border-amber-500/25 bg-amber-950/40 px-4 py-3 text-sm text-amber-100"
          >
            {error}
          </p>
        ) : null}

        <button type="submit" disabled={isSubmitting} className={AUTH_PRIMARY_BUTTON_CLASS}>
          {isSubmitting ? (
            <>
              <AuthLoadingSpinner />
              Sending…
            </>
          ) : (
            "Send reset instructions"
          )}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-zinc-500">
        Remembered your password?{" "}
        <Link
          href={withPreservedReturnUrl("/login", returnUrl)}
          className={AUTH_SECONDARY_LINK_CLASS}
        >
          Sign in
        </Link>
      </p>
    </>
  );
}
