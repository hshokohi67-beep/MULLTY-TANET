import 'server-only';
import { cookies } from 'next/headers';

/**
 * The staff API token never reaches browser JavaScript: it lives in an HttpOnly cookie
 * and only this Next.js server (the BFF) attaches it to API calls.
 */
const TOKEN_COOKIE = 'cs_staff_token';
const TENANT_COOKIE = 'cs_tenant';
const MAX_AGE_SECONDS = 12 * 60 * 60; // matches STAFF_TOKEN_HOURS in the API

const baseOptions = {
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'lax' as const,
  path: '/',
};

export async function getStaffToken(): Promise<string | undefined> {
  return (await cookies()).get(TOKEN_COOKIE)?.value;
}

export async function getTenantSlug(): Promise<string | undefined> {
  return (await cookies()).get(TENANT_COOKIE)?.value;
}

/** Only callable from Server Functions / Route Handlers. */
export async function startSession(token: string): Promise<void> {
  (await cookies()).set(TOKEN_COOKIE, token, { ...baseOptions, maxAge: MAX_AGE_SECONDS });
}

export async function selectTenant(slug: string): Promise<void> {
  (await cookies()).set(TENANT_COOKIE, slug, { ...baseOptions, maxAge: 30 * 24 * 60 * 60 });
}

export async function endSession(): Promise<void> {
  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(TENANT_COOKIE);
}

export const SESSION_COOKIE_NAME = TOKEN_COOKIE;
