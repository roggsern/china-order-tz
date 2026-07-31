"use client";

import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerChangePasswordError,
  changeCustomerPassword,
} from "@/lib/api/customer-change-password";
import {
  mapChangePasswordError,
  mapChangePasswordSuccess,
  validateChangePasswordForm,
} from "@/lib/account/customer-change-password";
import {
  CustomerEmailChangeError,
  confirmCustomerEmailChange,
  fetchCustomerSecurityProfile,
  requestCustomerEmailChange,
  type CustomerProfileSecurity,
} from "@/lib/api/customer-email-change";
import {
  mapEmailChangeConfirmSuccess,
  mapEmailChangeError,
  mapEmailChangeRequestSuccess,
  validateEmailChangeForm,
} from "@/lib/account/customer-email-change";
import {
  CustomerEmailVerificationError,
  resendEmailVerification,
} from "@/lib/api/customer-email-verification";
import { mapResendVerificationSuccess } from "@/lib/account/customer-email-verification";
import { logoutCustomer } from "@/lib/customer/logout-customer";
import { saveCustomerSession } from "@/lib/customer/session";
import { getPasswordStrength, PASSWORD_STRENGTH_META } from "@/lib/auth/password-strength";
import { withPreservedReturnUrl } from "@/lib/auth/return-url";

export function AccountSecurityContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { isReady, isLoggedIn, session } = useCustomerSession();

  const [profile, setProfile] = useState<CustomerProfileSecurity | null>(null);
  const [loadingProfile, setLoadingProfile] = useState(true);
  const [profileError, setProfileError] = useState<string | null>(null);

  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordSuccess, setPasswordSuccess] = useState<string | null>(null);
  const [passwordSubmitting, setPasswordSubmitting] = useState(false);

  const [newEmail, setNewEmail] = useState("");
  const [emailPassword, setEmailPassword] = useState("");
  const [emailError, setEmailError] = useState<string | null>(null);
  const [emailSuccess, setEmailSuccess] = useState<string | null>(null);
  const [emailSubmitting, setEmailSubmitting] = useState(false);
  const [confirmBusy, setConfirmBusy] = useState(false);
  const [resendBusy, setResendBusy] = useState(false);
  const [resendMessage, setResendMessage] = useState<string | null>(null);
  const confirmAttempted = useRef(false);

  const strength = getPasswordStrength(password);
  const emailChangeToken = searchParams.get("email_change_token")?.trim() || "";

  const reloadProfile = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setProfile(null);
      setLoadingProfile(false);
      return;
    }
    setLoadingProfile(true);
    setProfileError(null);
    try {
      const data = await fetchCustomerSecurityProfile();
      setProfile(data);
    } catch (err) {
      setProfileError(
        err instanceof CustomerEmailChangeError
          ? err.message
          : "Unable to load account security details.",
      );
    } finally {
      setLoadingProfile(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    if (!isLoggedIn || !getCustomerApiToken()) {
      router.replace(withPreservedReturnUrl("/login", "/account/security"));
      return;
    }
    void reloadProfile();
  }, [isReady, isLoggedIn, router, reloadProfile]);

  useEffect(() => {
    if (!isReady || !emailChangeToken || confirmAttempted.current) return;
    confirmAttempted.current = true;
    setConfirmBusy(true);
    setEmailError(null);
    void confirmCustomerEmailChange(emailChangeToken)
      .then(async (result) => {
        setEmailSuccess(mapEmailChangeConfirmSuccess(result.message));
        if (result.email && session) {
          saveCustomerSession({ ...session, email: result.email });
          window.dispatchEvent(new Event("customer-session-updated"));
        }
        await reloadProfile();
        router.replace("/account/security");
      })
      .catch((err) => {
        setEmailError(
          mapEmailChangeError(
            err instanceof CustomerEmailChangeError
              ? err.message
              : "Unable to confirm email change.",
          ),
        );
      })
      .finally(() => {
        setConfirmBusy(false);
      });
  }, [isReady, emailChangeToken, reloadProfile, router, session]);

  const handleResendVerification = async () => {
    setResendBusy(true);
    setResendMessage(null);
    setEmailError(null);
    try {
      const result = await resendEmailVerification();
      setResendMessage(mapResendVerificationSuccess(result.message));
      await reloadProfile();
    } catch (err) {
      setEmailError(
        err instanceof CustomerEmailVerificationError
          ? err.message
          : "Unable to resend verification email.",
      );
    } finally {
      setResendBusy(false);
    }
  };

  const handlePasswordSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setPasswordError(null);

    const validationError = validateChangePasswordForm({
      currentPassword,
      password,
      passwordConfirmation,
    });
    if (validationError) {
      setPasswordError(validationError);
      return;
    }

    setPasswordSubmitting(true);
    try {
      const result = await changeCustomerPassword({
        currentPassword,
        password,
        passwordConfirmation,
      });
      setPasswordSuccess(mapChangePasswordSuccess(result.message));
      setCurrentPassword("");
      setPassword("");
      setPasswordConfirmation("");
      await logoutCustomer({ showToast: false });
      window.setTimeout(() => {
        router.push(withPreservedReturnUrl("/login", "/account"));
      }, 1400);
    } catch (err) {
      setPasswordError(
        mapChangePasswordError(
          err instanceof CustomerChangePasswordError
            ? err.message
            : "Unable to change password. Please try again.",
        ),
      );
    } finally {
      setPasswordSubmitting(false);
    }
  };

  const handleEmailSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setEmailError(null);
    setEmailSuccess(null);

    const validationError = validateEmailChangeForm({
      newEmail,
      currentPassword: emailPassword,
    });
    if (validationError) {
      setEmailError(validationError);
      return;
    }

    setEmailSubmitting(true);
    try {
      const result = await requestCustomerEmailChange({
        newEmail,
        currentPassword: emailPassword,
      });
      setEmailSuccess(mapEmailChangeRequestSuccess(result.message));
      setNewEmail("");
      setEmailPassword("");
      await reloadProfile();
    } catch (err) {
      setEmailError(
        mapEmailChangeError(
          err instanceof CustomerEmailChangeError
            ? err.message
            : "Unable to change email. Please try again.",
        ),
      );
    } finally {
      setEmailSubmitting(false);
    }
  };

  const verified = Boolean(profile?.email_verified_at);
  const pendingEmail = profile?.pending_email?.trim() || null;

  return (
    <div className="mx-auto max-w-xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <nav aria-label="Breadcrumb" className="mb-6">
        <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
          <li>
            <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
              My Account
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li className="font-semibold text-zinc-900">Security</li>
        </ol>
      </nav>

      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-zinc-900">Account Security</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Manage your email and password. Email changes require confirmation before they take
          effect.
        </p>
      </div>

      {!isReady || loadingProfile ? <AccountPageSkeleton /> : null}

      {profileError ? (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {profileError}
        </div>
      ) : null}

      {isReady && !loadingProfile && profile ? (
        <section className="mb-8 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-zinc-900">Email</h2>
          <dl className="mt-4 space-y-2 text-sm">
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                Current email
              </dt>
              <dd className="mt-1 font-medium text-zinc-900">{profile.email}</dd>
            </div>
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</dt>
                <dd className="mt-1 flex flex-wrap items-center gap-2">
                  {verified ? (
                    <span className="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                      Verified
                    </span>
                  ) : (
                    <>
                      <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                        Unverified
                      </span>
                      <button
                        type="button"
                        disabled={resendBusy}
                        onClick={() => void handleResendVerification()}
                        className="text-xs font-semibold text-[#8b6914] hover:text-[#c9a227] disabled:opacity-50"
                      >
                        {resendBusy ? "Sending…" : "Resend verification"}
                      </button>
                    </>
                  )}
                </dd>
              </div>
              {resendMessage ? (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                  {resendMessage}
                </div>
              ) : null}
            {pendingEmail ? (
              <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                <p className="font-semibold">Pending confirmation</p>
                <p className="mt-1">
                  Waiting for confirmation of <strong>{pendingEmail}</strong>. Check that inbox for
                  the confirmation link.
                </p>
              </div>
            ) : null}
          </dl>

          {emailSuccess ? (
            <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
              {emailSuccess}
            </div>
          ) : null}

          <form onSubmit={handleEmailSubmit} className="mt-5 space-y-4 border-t border-zinc-100 pt-5">
            <h3 className="text-sm font-semibold text-zinc-900">Change email</h3>
            {emailError ? (
              <div
                role="alert"
                className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              >
                {emailError}
              </div>
            ) : null}
            <label className="block text-sm">
              <span className="font-medium text-zinc-700">New email</span>
              <input
                type="email"
                autoComplete="email"
                value={newEmail}
                onChange={(e) => setNewEmail(e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                required
              />
            </label>
            <label className="block text-sm">
              <span className="font-medium text-zinc-700">Current password</span>
              <input
                type="password"
                autoComplete="current-password"
                value={emailPassword}
                onChange={(e) => setEmailPassword(e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                required
              />
            </label>
            <button
              type="submit"
              disabled={emailSubmitting || confirmBusy}
              className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#6f5410] disabled:opacity-50"
            >
              {emailSubmitting ? "Sending…" : "Request email change"}
            </button>
          </form>
        </section>
      ) : null}

      {passwordSuccess ? (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-5 text-sm text-emerald-800">
          <p className="font-semibold">{passwordSuccess}</p>
          <p className="mt-1">Redirecting you to sign in…</p>
        </div>
      ) : null}

      {isReady && !passwordSuccess ? (
        <form
          onSubmit={handlePasswordSubmit}
          className="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm"
        >
          <h2 className="text-lg font-semibold text-zinc-900">Change password</h2>
          <p className="text-sm text-zinc-500">
            You will be signed out on all devices after a successful change.
          </p>
          {passwordError ? (
            <div
              role="alert"
              className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              {passwordError}
            </div>
          ) : null}

          <label className="block text-sm">
            <span className="font-medium text-zinc-700">Current password</span>
            <input
              type="password"
              autoComplete="current-password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
              required
            />
          </label>

          <label className="block text-sm">
            <span className="font-medium text-zinc-700">New password</span>
            <input
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
              required
              minLength={8}
            />
            {strength ? (
              <p className={`mt-1 text-xs ${PASSWORD_STRENGTH_META[strength].textClass}`}>
                Strength: {PASSWORD_STRENGTH_META[strength].label}
              </p>
            ) : null}
          </label>

          <label className="block text-sm">
            <span className="font-medium text-zinc-700">Confirm new password</span>
            <input
              type="password"
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
              required
              minLength={8}
            />
          </label>

          <button
            type="submit"
            disabled={passwordSubmitting}
            className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#6f5410] disabled:opacity-50"
          >
            {passwordSubmitting ? "Updating…" : "Update password"}
          </button>
        </form>
      ) : null}
    </div>
  );
}
