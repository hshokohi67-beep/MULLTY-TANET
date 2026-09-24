import 'server-only';
import { cookies } from 'next/headers';
import { ApiError } from './api';

/**
 * Kitchen screens authenticate as a paired device (its own HttpOnly cookies) or, if none, as the
 * signed-in staff member. Tokens never reach browser JavaScript.
 */
const DEVICE_TOKEN = 'cs_kds_token';
const DEVICE_TENANT = 'cs_kds_tenant';
const API_URL = process.env.API_URL ?? 'http://127.0.0.1:8000';

const cookieOptions = {
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'lax' as const,
  path: '/',
  maxAge: 365 * 24 * 60 * 60, // a paired tablet stays paired until revoked
};

export async function kdsCredentials(): Promise<{ token: string; tenant: string; device: boolean } | null> {
  const store = await cookies();
  const deviceToken = store.get(DEVICE_TOKEN)?.value;
  const deviceTenant = store.get(DEVICE_TENANT)?.value;

  if (deviceToken && deviceTenant) return { token: deviceToken, tenant: deviceTenant, device: true };

  const staffToken = store.get('cs_staff_token')?.value;
  const staffTenant = store.get('cs_tenant')?.value;

  return staffToken && staffTenant ? { token: staffToken, tenant: staffTenant, device: false } : null;
}

export async function storeDevice(token: string, tenant: string): Promise<void> {
  const store = await cookies();
  store.set(DEVICE_TOKEN, token, cookieOptions);
  store.set(DEVICE_TENANT, tenant, cookieOptions);
}

export async function forgetDevice(): Promise<void> {
  const store = await cookies();
  store.delete(DEVICE_TOKEN);
  store.delete(DEVICE_TENANT);
}

interface KdsRequest {
  method?: 'GET' | 'POST';
  body?: unknown;
  etag?: string | null;
}

/** Raw call so the board route can pass ETag/304 through. */
export async function kdsFetch(path: string, { method = 'GET', body, etag }: KdsRequest = {}): Promise<Response> {
  const creds = await kdsCredentials();
  if (!creds) throw new ApiError(401, 'unauthenticated', 'این دستگاه به آشپزخانه متصل نیست.');

  const headers: Record<string, string> = { Accept: 'application/json', Authorization: `Bearer ${creds.token}`, 'X-Tenant': creds.tenant };
  if (etag) headers['If-None-Match'] = etag;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  try {
    return await fetch(`${API_URL}/api/v1${path}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body), cache: 'no-store' });
  } catch {
    throw new ApiError(503, 'api_unreachable', 'ارتباط با سرور برقرار نشد.');
  }
}

export async function kdsApi<T>(path: string, request: KdsRequest = {}): Promise<T> {
  const response = await kdsFetch(path, request);
  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = (data ?? {}) as { code?: string; message?: string };
    throw new ApiError(response.status, error.code ?? `http_${response.status}`, error.message ?? 'خطایی رخ داد.');
  }

  return data as T;
}
