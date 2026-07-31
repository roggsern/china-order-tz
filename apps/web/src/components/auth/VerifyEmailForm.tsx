"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
  AUTH_PRIMARY_BUTTON_CLASS,
  AUTH_SECONDARY_LINK_CLASS,
} from "@/components/auth/auth-styles";
import { AuthLoadingSpinner } from "@/components/auth/AuthLoadingSpinner";
import {
  mapVerifyEmailError,
  mapVerifyEmailSuccess,
  parseVerifyEmailQuery,
  validateVerifyEmailQuery,
} from "@/lib/account/customer-email-verification";
import {
  CustomerEmailVerificationError,
  confirmEmailVerification,
} from "@/lib/api/customer-email-verification";
import { withPreservedReturnUrl } from "@/lib/auth/return-url";

export function VerifyEmailForm() {
  const searchParams = useSearchParams();
  const returnUrl = searchParams.get("returnUrl");
  const query = useMemo(() => parseVerifyEmailQuery(searchParams), [searchParams]);

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [autoStarted, setAutoStarted] = useState(false);

  const incompleteMessage = validateVerifyEmailQuery(query);

  const runVerify = async () => {
    const validationError = validateVerifyEmailQuery(query);
    if (validationError) {
      setError(validationError);
      return;
    }

    setIsSubmitting(true);
    setError(null);
    try {
      const result = await confirmEmailVerification(query);
      setSuccess(mapVerifyEmailSuccess(result.message, result.alreadyVerified));
    } catch (err) {
      setError(
        mapVerifyEmailError(
          err instanceof CustomerEmailVerificationError
            ? err.message
            : "Unable to verify email. The link may be invalid or expired.",
        ),
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  useEffect(() => {
    if (autoStarted || incompleteMessage) return;
    setAutoStarted(true);
    void runVerify();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoStarted, incompleteMessage]);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    await runVerify();
  };

  if (success) {
    return (
      <div className="space-y-5 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
          <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <p className="text-sm text-zinc-300">{success}</p>
        <Link href={withPreservedReturnUrl("/account/security", returnUrl)} className={AUTH_PRIMARY_BUTTON_CLASS}>
          Account security
        </Link>
        <Link href={withPreservedReturnUrl("/login", returnUrl)} className={AUTH_SECONDARY_LINK_CLASS}>
          Sign in
        </Link>
      </div>
    );
  }

  if (incompleteMessage && !isSubmitting) {
    return (
      <div className="space-y-5 text-center">
        <p className="text-sm text-zinc-400">{incompleteMessage}</p>
        <Link
          href={withPreservedReturnUrl("/account/security", returnUrl)}
          className={AUTH_PRIMARY_BUTTON_CLASS}
        >
          Go to account security
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      {error ? (
        <p role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
          {error}
        </p>
      ) : null}
      <p className="text-sm text-zinc-400">
        {isSubmitting
          ? "Verifying your email address…"
          : "Click below if verification did not start automatically."}
      </p>
      <button type="submit" disabled={isSubmitting} className={AUTH_PRIMARY_BUTTON_CLASS}>
        {isSubmitting ? (
          <span className="inline-flex items-center justify-center gap-2">
            <AuthLoadingSpinner />
            Verifying…
          </span>
        ) : (
          "Verify email"
        )}
      </button>
    </form>
  );
}
