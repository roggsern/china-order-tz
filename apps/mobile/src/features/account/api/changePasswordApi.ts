import { apiClient } from '@/src/core/api';

export type ChangePasswordInput = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

export type ChangePasswordResult = {
  success: boolean;
  message: string;
  requiresReauthentication: boolean;
};

/**
 * POST /account/change-password
 * Server revokes tokens; client must clear session and re-authenticate.
 */
export async function changePassword(
  input: ChangePasswordInput,
): Promise<ChangePasswordResult> {
  const response = await apiClient.post<{
    message?: string;
    requires_reauthentication?: boolean;
  }>('/account/change-password', input);

  const body = response as unknown as {
    success?: boolean;
    message?: string;
    requires_reauthentication?: boolean;
  };

  return {
    success: body.success === true,
    message:
      typeof body.message === 'string' && body.message.trim() !== ''
        ? body.message
        : 'Password updated.',
    requiresReauthentication: body.requires_reauthentication !== false,
  };
}
