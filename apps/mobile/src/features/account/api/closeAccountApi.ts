import { apiClient } from '@/src/core/api';

export type CloseAccountInput = {
  current_password: string;
  acknowledge: boolean;
};

export type CloseAccountResult = {
  success: boolean;
  message: string;
  requiresReauthentication: boolean;
  alreadyClosed: boolean;
};

/**
 * POST /account/close — authoritative hybrid account closure.
 * Server revokes tokens; client must clear local session.
 */
export async function closeAccount(
  input: CloseAccountInput,
): Promise<CloseAccountResult> {
  const response = await apiClient.post<{
    success?: boolean;
    message?: string;
    requires_reauthentication?: boolean;
    already_closed?: boolean;
  }>('/account/close', input);

  const body = response as unknown as {
    success?: boolean;
    message?: string;
    requires_reauthentication?: boolean;
    already_closed?: boolean;
  };

  return {
    success: body.success === true,
    message:
      typeof body.message === 'string' && body.message.trim() !== ''
        ? body.message
        : 'Your account has been closed.',
    requiresReauthentication: body.requires_reauthentication !== false,
    alreadyClosed: body.already_closed === true,
  };
}
