import { z } from 'zod';

/** Contract v1 success envelope (data may be null). */
export const apiSuccessSchema = z.object({
  success: z.literal(true),
  message: z.string().optional(),
  data: z.unknown().optional(),
  meta: z.unknown().optional(),
  request_id: z.string().optional(),
});

export type ApiSuccessEnvelope<T = unknown> = {
  success: true;
  message?: string;
  data: T;
  meta?: unknown;
  request_id?: string;
  [key: string]: unknown;
};

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type ApiRequestOptions = {
  method?: HttpMethod;
  path: string;
  query?: Record<string, string | number | boolean | null | undefined>;
  body?: unknown;
  /** Override bearer token for this request (null = omit Authorization). */
  token?: string | null;
  signal?: AbortSignal;
  /** Override default API request timeout (ms). */
  timeoutMs?: number;
  headers?: Record<string, string>;
};
