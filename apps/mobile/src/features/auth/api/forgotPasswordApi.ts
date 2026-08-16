import { z } from 'zod';
import { apiClient } from '@/src/core/api';

export const forgotPasswordRequestSchema = z.object({
  email: z.string().email('Enter a valid email'),
});

export type ForgotPasswordRequest = z.infer<typeof forgotPasswordRequestSchema>;

export type ForgotPasswordResult = {
  success: boolean;
  message: string;
};

const forgotPasswordResponseSchema = z.object({
  success: z.boolean().optional(),
  message: z.string().optional(),
});

/**
 * POST /auth/forgot-password
 * Always returns a neutral acknowledgement (backend never reveals account existence).
 * Reset completion is via Resend email → production web /reset-password.
 */
export async function requestPasswordReset(
  input: ForgotPasswordRequest,
): Promise<ForgotPasswordResult> {
  const response = await apiClient.post<unknown>(
    '/auth/forgot-password',
    { email: input.email.trim().toLowerCase() },
    null,
  );

  const parsed = forgotPasswordResponseSchema.safeParse(response);
  const message =
    parsed.success && typeof parsed.data.message === 'string' && parsed.data.message.trim()
      ? parsed.data.message.trim()
      : 'If an account exists for that email, password reset instructions have been sent.';

  return {
    success: parsed.success ? parsed.data.success !== false : true,
    message,
  };
}
