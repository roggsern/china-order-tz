"use client";

import { FormEvent, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { saveCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerRegisterError,
  registerCustomer,
} from "@/lib/api/customer-register";
import {
  getPasswordStrength,
  getPasswordStrengthWidth,
  PASSWORD_STRENGTH_META,
} from "@/lib/auth/password-strength";
import { toFriendlyAuthMessage } from "@/lib/auth/friendly-auth-messages";
import { resolvePostAuthRedirect, withPreservedReturnUrl } from "@/lib/auth/return-url";
import { queueCustomerToast } from "@/lib/customer/customer-toast";
import {
  isValidPhoneForCountry,
  normalizePhoneForBackend,
  PHONE_VALIDATION_MESSAGE,
} from "@/lib/customer/normalize-phone";
import { DEFAULT_PHONE_COUNTRY_ISO } from "@/lib/customer/phone-countries";
import { saveCustomerSession } from "@/lib/customer/session";
import { InternationalPhoneInput } from "@/components/auth/InternationalPhoneInput";
import {
  AUTH_INPUT_CLASS,
  AUTH_LABEL_CLASS,
  AUTH_PRIMARY_BUTTON_CLASS,
  AUTH_SECONDARY_LINK_CLASS,
} from "@/components/auth/auth-styles";
import { AuthLoadingSpinner } from "@/components/auth/AuthLoadingSpinner";

const inputClassName = AUTH_INPUT_CLASS;
const labelClassName = AUTH_LABEL_CLASS;

type FieldErrors = Record<string, string>;

function mapFriendlyFieldErrors(errors?: Record<string, string[]>): FieldErrors {
  if (!errors) {
    return {};
  }

  const mapped: FieldErrors = {};

  for (const [key, messages] of Object.entries(errors)) {
    const message = messages.find((entry) => entry.trim())?.trim();
    if (!message) {
      continue;
    }

    if (key === "name") {
      mapped.firstName = "Please enter your first and last name.";
      continue;
    }

    if (key === "email") {
      if (/taken|unique|already/i.test(message)) {
        mapped.email = "This email is already registered.";
      } else if (/valid|format/i.test(message)) {
        mapped.email = "Please enter a valid email address.";
      } else {
        mapped.email = "Please enter your email.";
      }
      continue;
    }

    if (key === "phone") {
      mapped.phone = "Please enter a valid phone number.";
      continue;
    }

    if (key === "password") {
      if (/8|characters|min/i.test(message)) {
        mapped.password = "Password must be at least 8 characters.";
      } else {
        mapped.password = "Please choose a stronger password.";
      }
      continue;
    }

    if (key === "password_confirmation") {
      mapped.confirmPassword = "Passwords do not match.";
      continue;
    }

    mapped[key] = message;
  }

  return mapped;
}

function EyeIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M2.036 12.322a1 1 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
      />
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  );
}

function EyeSlashIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c1.841 0 3.575-.487 5.07-1.34M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
      />
    </svg>
  );
}

function LoadingSpinner({ className }: { className?: string }) {
  return <AuthLoadingSpinner className={className} />;
}

function CheckIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
  );
}

export function RegisterForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const returnUrl = searchParams.get("returnUrl");
  const [error, setError] = useState<string | undefined>();
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [phoneCountryIso, setPhoneCountryIso] = useState(DEFAULT_PHONE_COUNTRY_ISO);
  const [phoneValue, setPhoneValue] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const passwordStrength = useMemo(() => getPasswordStrength(password), [password]);

  const passwordsMatch =
    confirmPassword.length > 0 && password.length > 0 && password === confirmPassword;
  const passwordsMismatch =
    confirmPassword.length > 0 && password.length > 0 && password !== confirmPassword;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(undefined);
    setFieldErrors({});

    const formData = new FormData(event.currentTarget);
    const firstName = String(formData.get("firstName") ?? "").trim();
    const lastName = String(formData.get("lastName") ?? "").trim();
    const email = String(formData.get("email") ?? "").trim();
    const rawPhone = phoneValue.trim();
    const nextPassword = String(formData.get("password") ?? "");
    const nextConfirmPassword = String(formData.get("confirmPassword") ?? "");

    const nextFieldErrors: FieldErrors = {};

    if (!firstName) {
      nextFieldErrors.firstName = "Please enter your first name.";
    }

    if (!lastName) {
      nextFieldErrors.lastName = "Please enter your last name.";
    }

    if (!email) {
      nextFieldErrors.email = "Please enter your email.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      nextFieldErrors.email = "Please enter a valid email address.";
    }

    if (rawPhone && !isValidPhoneForCountry(phoneCountryIso, rawPhone)) {
      nextFieldErrors.phone = PHONE_VALIDATION_MESSAGE;
    }

    if (!nextPassword) {
      nextFieldErrors.password = "Please enter a password.";
    } else if (nextPassword.length < 8) {
      nextFieldErrors.password = "Password must be at least 8 characters.";
    }

    if (!nextConfirmPassword) {
      nextFieldErrors.confirmPassword = "Please confirm your password.";
    } else if (nextPassword !== nextConfirmPassword) {
      nextFieldErrors.confirmPassword = "Passwords do not match.";
    }

    if (Object.keys(nextFieldErrors).length > 0) {
      setFieldErrors(nextFieldErrors);
      return;
    }

    const normalizedPhone = rawPhone
      ? normalizePhoneForBackend(phoneCountryIso, rawPhone)
      : "";

    setIsSubmitting(true);

    try {
      const result = await registerCustomer({
        firstName,
        lastName,
        email,
        phone: normalizedPhone,
        password: nextPassword,
        passwordConfirmation: nextConfirmPassword,
      });

      saveCustomerApiToken(result.token);
      saveCustomerSession({
        email: result.user.email,
        name: result.user.name,
      });
      queueCustomerToast(
        "🎉 Welcome to CHINA ORDER TZ!\nYour account has been created successfully.",
      );
      window.dispatchEvent(new Event("customer-session-updated"));
      // Cart lives in localStorage — register does not clear it.
      router.push(resolvePostAuthRedirect(returnUrl));
    } catch (registerError) {
      if (registerError instanceof CustomerRegisterError) {
        setError(
          toFriendlyAuthMessage(
            registerError.message,
            "Unable to create your account. Please try again.",
          ),
        );
        setFieldErrors(mapFriendlyFieldErrors(registerError.fieldErrors));
      } else if (registerError instanceof Error) {
        setError(
          toFriendlyAuthMessage(
            registerError.message,
            "Unable to create your account. Please try again.",
          ),
        );
      } else {
        setError("Unable to create your account. Please try again.");
      }
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <form className="space-y-4" onSubmit={handleSubmit} noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="firstName" className={labelClassName}>
              First Name
            </label>
            <input
              id="firstName"
              name="firstName"
              type="text"
              autoComplete="given-name"
              required
              disabled={isSubmitting}
              className={inputClassName}
              placeholder="Jane"
              aria-invalid={Boolean(fieldErrors.firstName)}
              aria-describedby={fieldErrors.firstName ? "firstName-error" : undefined}
            />
            {fieldErrors.firstName ? (
              <p id="firstName-error" className="mt-1.5 text-xs text-red-300">
                {fieldErrors.firstName}
              </p>
            ) : null}
          </div>

          <div>
            <label htmlFor="lastName" className={labelClassName}>
              Last Name
            </label>
            <input
              id="lastName"
              name="lastName"
              type="text"
              autoComplete="family-name"
              required
              disabled={isSubmitting}
              className={inputClassName}
              placeholder="Customer"
              aria-invalid={Boolean(fieldErrors.lastName)}
              aria-describedby={fieldErrors.lastName ? "lastName-error" : undefined}
            />
            {fieldErrors.lastName ? (
              <p id="lastName-error" className="mt-1.5 text-xs text-red-300">
                {fieldErrors.lastName}
              </p>
            ) : null}
          </div>
        </div>

        <div>
          <label htmlFor="email" className={labelClassName}>
            Email Address
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            disabled={isSubmitting}
            className={inputClassName}
            placeholder="you@example.com"
            aria-invalid={Boolean(fieldErrors.email)}
            aria-describedby={fieldErrors.email ? "email-error" : undefined}
          />
          {fieldErrors.email ? (
            <p id="email-error" className="mt-1.5 text-xs text-red-300">
              {fieldErrors.email}
            </p>
          ) : null}
        </div>

        <InternationalPhoneInput
          id="phone"
          value={phoneValue}
          countryIso={phoneCountryIso}
          onValueChange={setPhoneValue}
          onCountryChange={setPhoneCountryIso}
          disabled={isSubmitting}
          error={fieldErrors.phone}
        />

        <div>
          <label htmlFor="password" className={labelClassName}>
            Password
          </label>
          <div className="relative mt-1.5">
            <input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="new-password"
              required
              disabled={isSubmitting}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className={`${inputClassName} mt-0 pr-12`}
              placeholder="At least 8 characters"
              aria-invalid={Boolean(fieldErrors.password)}
              aria-describedby={
                fieldErrors.password
                  ? "password-error"
                  : passwordStrength
                    ? "password-strength"
                    : undefined
              }
            />
            <button
              type="button"
              onClick={() => setShowPassword((current) => !current)}
              disabled={isSubmitting}
              className="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 transition hover:text-zinc-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227]/40 disabled:cursor-not-allowed disabled:opacity-60"
              aria-label={showPassword ? "Hide password" : "Show password"}
            >
              {showPassword ? (
                <EyeSlashIcon className="h-5 w-5" />
              ) : (
                <EyeIcon className="h-5 w-5" />
              )}
            </button>
          </div>

          {passwordStrength ? (
            <div id="password-strength" className="mt-2" aria-live="polite">
              <div className="h-1.5 overflow-hidden rounded-full bg-zinc-800">
                <div
                  className={`h-full rounded-full transition-all duration-300 ${PASSWORD_STRENGTH_META[passwordStrength].barClass}`}
                  style={{ width: getPasswordStrengthWidth(passwordStrength) }}
                />
              </div>
              <p
                className={`mt-1.5 text-xs font-medium ${PASSWORD_STRENGTH_META[passwordStrength].textClass}`}
              >
                {PASSWORD_STRENGTH_META[passwordStrength].label}
              </p>
            </div>
          ) : null}

          {fieldErrors.password ? (
            <p id="password-error" className="mt-1.5 text-xs text-red-300">
              {fieldErrors.password}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="confirmPassword" className={labelClassName}>
            Confirm Password
          </label>
          <div className="relative mt-1.5">
            <input
              id="confirmPassword"
              name="confirmPassword"
              type={showConfirmPassword ? "text" : "password"}
              autoComplete="new-password"
              required
              disabled={isSubmitting}
              value={confirmPassword}
              onChange={(event) => setConfirmPassword(event.target.value)}
              className={`${inputClassName} mt-0 pr-12`}
              placeholder="Re-enter your password"
              aria-invalid={Boolean(fieldErrors.confirmPassword || passwordsMismatch)}
              aria-describedby={
                fieldErrors.confirmPassword || passwordsMatch || passwordsMismatch
                  ? "confirmPassword-feedback"
                  : undefined
              }
            />
            <button
              type="button"
              onClick={() => setShowConfirmPassword((current) => !current)}
              disabled={isSubmitting}
              className="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 transition hover:text-zinc-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227]/40 disabled:cursor-not-allowed disabled:opacity-60"
              aria-label={showConfirmPassword ? "Hide confirm password" : "Show confirm password"}
            >
              {showConfirmPassword ? (
                <EyeSlashIcon className="h-5 w-5" />
              ) : (
                <EyeIcon className="h-5 w-5" />
              )}
            </button>
          </div>

          {fieldErrors.confirmPassword ? (
            <p id="confirmPassword-feedback" className="mt-1.5 text-xs text-red-300">
              {fieldErrors.confirmPassword}
            </p>
          ) : passwordsMatch ? (
            <p
              id="confirmPassword-feedback"
              className="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-emerald-400"
            >
              <CheckIcon className="h-4 w-4" />
              Passwords match
            </p>
          ) : passwordsMismatch ? (
            <p id="confirmPassword-feedback" className="mt-1.5 text-xs text-red-300">
              Passwords do not match
            </p>
          ) : null}
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
              <LoadingSpinner className="h-5 w-5 animate-spin" />
              Creating account…
            </>
          ) : (
            "Create account"
          )}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-zinc-500">
        Already have an account?{" "}
        <Link
          href={withPreservedReturnUrl("/login", returnUrl)}
          className={AUTH_SECONDARY_LINK_CLASS}
        >
          Sign In
        </Link>
      </p>
    </>
  );
}
