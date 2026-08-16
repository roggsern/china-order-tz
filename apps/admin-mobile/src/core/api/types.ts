export type ApiSuccessEnvelope<T = unknown> = {
  success: true;
  data: T;
  message?: string;
  meta?: PaginationMeta;
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  total: number;
  per_page?: number;
};

export type ApiRequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  path: string;
  body?: unknown;
  query?: Record<string, string | number | boolean | null | undefined>;
  headers?: Record<string, string>;
  token?: string | null;
  signal?: AbortSignal;
  timeoutMs?: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
};
