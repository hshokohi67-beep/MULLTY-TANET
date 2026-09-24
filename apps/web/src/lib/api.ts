import 'server-only';
import type { ApiErrorBody, FormState } from './types';
import { getStaffToken, getTenantSlug } from './session';

const API_URL = process.env.API_URL ?? 'http://127.0.0.1:8000';

/** An API failure carrying the server's Persian message and stable error code. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  formData?: FormData;
  /** Send the dashboard's selected tenant (X-Tenant). Default true; a string forces a slug. */
  tenant?: boolean | string;
  /** Attach the staff token. Default true. */
  auth?: boolean;
  /** Forward the visitor's host for storefront resolution (X-Tenant-Domain). */
  tenantDomain?: string;
  revalidate?: number;
  /** Extra request headers (e.g. X-Order-Token for public order tracking). */
  headers?: Record<string, string>;
  /** A customer token (storefront); used instead of the staff token. */
  bearer?: string;
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, formData, tenant = true, auth = true, tenantDomain, revalidate } = options;
  const headers: Record<string, string> = { Accept: 'application/json', ...options.headers };

  if (options.bearer) {
    headers.Authorization = `Bearer ${options.bearer}`;
  } else if (auth) {
    const token = await getStaffToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }

  const tenantSlug = typeof tenant === 'string' ? tenant : tenant ? await getTenantSlug() : undefined;
  if (tenantSlug) headers['X-Tenant'] = tenantSlug;
  if (tenantDomain) headers['X-Tenant-Domain'] = tenantDomain;

  let payload: BodyInit | undefined;
  if (formData) {
    payload = formData;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/v1${path}`, {
      method,
      headers,
      body: payload,
      ...(revalidate === undefined ? { cache: 'no-store' as const } : { next: { revalidate } }),
    });
  } catch {
    throw new ApiError(503, 'api_unreachable', 'ارتباط با سرور برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید.');
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = (data ?? {}) as Partial<ApiErrorBody>;
    throw new ApiError(
      response.status,
      error.code ?? `http_${response.status}`,
      error.message ?? 'خطایی رخ داد. لطفاً چند لحظه بعد دوباره تلاش کنید.',
      error.errors ?? {},
    );
  }

  return data as T;
}

/**
 * A raw authenticated GET for streamed downloads (CSV export). The caller forwards the body as is,
 * so large files never sit in memory.
 */
export async function apiRaw(path: string): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'text/csv, application/json' };
  const token = await getStaffToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const tenantSlug = await getTenantSlug();
  if (tenantSlug) headers['X-Tenant'] = tenantSlug;

  return fetch(`${API_URL}/api/v1${path}`, { headers, cache: 'no-store' });
}

/** Converts an API failure into form state (first error per field) for useActionState. */
export function toFormState(error: unknown): FormState {
  if (error instanceof ApiError) {
    const errors = Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0] ?? '']));

    return { ok: false, message: error.message, errors };
  }

  throw error;
}
