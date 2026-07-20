"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { saveCustomerApiToken } from "@/lib/api/customer-auth";
import { CustomerLoginError, loginCustomer } from "@/lib/api/customer-login";
import { toFriendlyAuthMessage } from "@/lib/auth/friendly-auth-messages";
import { resolvePostAuthRedirect, withPreservedReturnUrl } from "@/lib/auth/return-url";
import { queueCustomerToast } from "@/lib/customer/customer-toast";
import { resolveCustomerDisplayName } from "@/lib/customer/display-name";
import { saveCustomerSession } from "@/lib/customer/session";
import {
  AUTH_INPUT_CLASS,
  AUTH_LABEL_CLASS,
  AUTH_PRIMARY_BUTTON_CLASS,
  AUTH_SECONDARY_LINK_CLASS,
} from "@/components/auth/auth-styles";
import { AuthLoadingSpinner } from "@/components/auth/AuthLoadingSpinner";

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const returnUrl = searchParams.get("returnUrl");
  const [error, setError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(undefined);

    const formData = new FormData(event.currentTarget);
    const email = String(formData.get("email") ?? "").trim();
    const password = String(formData.get("password") ?? "");

    if (!email || !password) {
      setError("Please enter your email and password.");
      return;
    }

    setIsSubmitting(true);

    try {
      const result = await loginCustomer({ email, password });

      saveCustomerApiToken(result.token);
      saveCustomerSession({
        email: result.user.email,
        name: result.user.name,
      });
      queueCustomerToast(
        `👋 Welcome back, ${resolveCustomerDisplayName(result.user.name, result.user.email)}!`,
      );
      window.dispatchEvent(new Event("customer-session-updated"));
      // Cart lives in localStorage — login does not clear it.
      router.push(resolvePostAuthRedirect(returnUrl));
    } catch (loginError) {
      if (loginError instanceof CustomerLoginError) {
        setError(toFriendlyAuthMessage(loginError.message, "Unable to sign in. Please try again."));
      } else if (loginError instanceof Error) {
        setError(toFriendlyAuthMessage(loginError.message, "Unable to sign in. Please try again."));
      } else {
        setError("Unable to sign in. Please try again.");
      }
      setIsSubmitting(false);
    }
  };

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
            className={AUTH_INPUT_CLASS}
            placeholder="you@example.com"
          />
        </div>

        <div>
          <div className="flex items-center justify-between gap-3">
            <label htmlFor="password" className={AUTH_LABEL_CLASS}>
              Password
            </label>
            <Link
              href={withPreservedReturnUrl("/forgot-password", returnUrl)}
              className="text-xs font-medium text-zinc-400 transition hover:text-[#e8c547]"
            >
              Forgot password?
            </Link>
          </div>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            disabled={isSubmitting}
            className={AUTH_INPUT_CLASS}
            placeholder="••••••••"
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
              Signing in…
            </>
          ) : (
            "Sign in"
          )}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-zinc-500">
        Don&apos;t have an account?{" "}
        <Link
          href={withPreservedReturnUrl("/register", returnUrl)}
          className={AUTH_SECONDARY_LINK_CLASS}
        >
          Create account
        </Link>
      </p>
    </>
  );
}
