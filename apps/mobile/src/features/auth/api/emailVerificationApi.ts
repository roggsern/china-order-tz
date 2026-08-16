import { apiClient } from '@/src/core/api';
import { userSchema, type User } from '@/src/shared/types/user';
import { fetchCurrentUser } from '@/src/features/account/api/profileApi';

export type ResendEmailVerificationResult = {
  success: boolean;
  message: string;
  alreadyVerified: boolean;
};

/**
 * POST /account/email/verify/resend — authenticated.
 * Sends verification via existing backend NotificationPlatform → Resend.
 * Link targets production web /verify-email (FRONTEND_URL).
 */
export async function resendEmailVerification(): Promise<ResendEmailVerificationResult> {
  const response = await apiClient.post<{
    success?: boolean;
    message?: string;
    already_verified?: boolean;
  }>('/account/email/verify/resend', {});

  const body = response as unknown as {
    success?: boolean;
    message?: string;
    already_verified?: boolean;
  };

  return {
    success: body.success !== false,
    message:
      typeof body.message === 'string' && body.message.trim() !== ''
        ? body.message.trim()
        : 'A verification link has been sent to your email address.',
    alreadyVerified: body.already_verified === true,
  };
}

/** Refresh signed-in user from GET /me (includes email_verified_at). */
export async function refreshAuthenticatedUser(): Promise<User> {
  const data = await fetchCurrentUser();
  const parsed = userSchema.safeParse(data);
  if (!parsed.success) {
    throw new Error('Could not refresh your account details.');
  }
  return parsed.data;
}
