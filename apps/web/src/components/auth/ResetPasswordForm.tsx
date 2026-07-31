"use client";

import { FormEvent, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  AUTH_INPUT_CLASS,
  AUTH_LABEL_CLASS,
  AUTH_PRIMARY_BUTTON_CLASS,
  AUTH_SECONDARY_LINK_CLASS,
} from "@/components/auth/auth-styles";
import { AuthLoadingSpinner } from "@/components/auth/AuthLoadingSpinner";
import { withPreservedReturnUrl } from "@/lib/auth/return-url";
import {
  mapResetPasswordSuccess,
  parseResetPasswordQuery,
  validateResetPasswordForm,
} from "@/lib/auth/customer-password-reset";
import {
  CustomerPasswordResetError,
  resetCustomerPassword,
} from "@/lib/api/customer-password-reset";
import { getPasswordStrength, PASSWORD_STRENGTH_META } from "@/lib/auth/password-strength";

export function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const returnUrl = searchParams.get("returnUrl");
  const initial = useMemo(() => parseResetPasswordQuery(searchParams), [searchParams]);

  const [email, setEmail] = useState(initial.email);
  const [token] = useState(initial.token);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const strength = getPasswordStrength(password);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(undefined);

    const validationError = validateResetPasswordForm({
      email,
      token,
      password,
      passwordConfirmation,
    });
    if (validationError) {
      setError(validationError);
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await resetCustomerPassword({
        email,
        token,
        password,
        passwordConfirmation,
      });
      setSuccessMessage(mapResetPasswordSuccess(result.message));
      window.setTimeout(() => {
        router.push(withPreservedReturnUrl("/login", returnUrl));
      }, 1600);
    } catch (err) {
      setError(
        err instanceof CustomerPasswordResetError
          ? err.message
          : "Unable to reset password. The link may be invalid or expired.",
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!token) {
    return (
      <div className="space-y-5 text-center">
        <p className="text-sm text-zinc-400">
          This reset link is incomplete. Request a new password reset email to continue.
        </p>
        <Link
          href={withPreservedReturnUrl("/forgot-password", returnUrl)}
          className={AUTH_PRIMARY_BUTTON_CLASS}
        >
          Request new link
        </Link>
      </div>
    );
  }

  if (successMessage) {
    return (
      <div className="space-y-5 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
          <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <div>
          <h2 className="text-lg font-bold text-white">Password updated</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">{successMessage}</p>
          <p className="mt-3 text-xs text-zinc-500">Redirecting you to sign in…</p>
        </div>
        <Link
          href={withPreservedReturnUrl("/login", returnUrl)}
          className={AUTH_PRIMARY_BUTTON_CLASS}
        >
          Sign in now
        </Link>
      </div>
    );
  }

  return (
    <>
      <form className="space-y-5" onSubmit={(event) => void handleSubmit(event)} noValidate>
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
          />
        </div>

        <div>
          <label htmlFor="password" className={AUTH_LABEL_CLASS}>
            New password
          </label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="new-password"
            required
            disabled={isSubmitting}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className={AUTH_INPUT_CLASS}
            placeholder="At least 8 characters"
          />
          {strength ? (
            <p className={`mt-2 text-xs ${PASSWORD_STRENGTH_META[strength].textClass}`}>
              Strength: {PASSWORD_STRENGTH_META[strength].label}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="password_confirmation" className={AUTH_LABEL_CLASS}>
            Confirm password
          </label>
          <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
            disabled={isSubmitting}
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            className={AUTH_INPUT_CLASS}
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
              Updating…
            </>
          ) : (
            "Reset password"
          )}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-zinc-500">
        <Link
          href={withPreservedReturnUrl("/login", returnUrl)}
          className={AUTH_SECONDARY_LINK_CLASS}
        >
          Back to sign in
        </Link>
      </p>
    </>
  );
}
