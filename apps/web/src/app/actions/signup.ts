'use server';

import { redirect } from 'next/navigation';
import { toLatinDigits } from '@cafe/locale';
import { api, ApiError } from '@/lib/api';
import { selectTenant, startSession } from '@/lib/session';

/** «شروع رایگان»: a café signs itself up. The API sends the code, checks everything and creates it. */

export interface SlugCheck { suggestion: string; slug: string | null; valid: boolean; available: boolean; base: string }
type Fail = { ok: false; message: string; errors?: Record<string, string> };

function fail(error: unknown): Fail {
  if (error instanceof ApiError) {
    return { ok: false, message: error.message, errors: Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? ''])) };
  }
  throw error;
}

export async function checkSlug(name: string, slug: string): Promise<SlugCheck | null> {
  const query = new URLSearchParams({ name: name.slice(0, 80), slug: slug.slice(0, 30) });
  try {
    return (await api<{ data: SlugCheck }>(`/public/signup/slug?${query.toString()}`, { auth: false, tenant: false })).data;
  } catch {
    return null;
  }
}

export async function sendSignupCode(phone: string, website: string): Promise<{ ok: true; resendAfter: number } | Fail> {
  try {
    const { data } = await api<{ data: { resend_after: number } }>('/public/signup/otp', {
      method: 'POST', auth: false, tenant: false, body: { phone: toLatinDigits(phone).trim().slice(0, 20), website },
    });

    return { ok: true, resendAfter: data.resend_after };
  } catch (error) {
    return fail(error);
  }
}

export async function signUp(input: { cafe_name: string; slug: string; owner_name: string; phone: string; password: string; code: string; website: string }): Promise<Fail> {
  let slug: string;
  try {
    const { data } = await api<{ data: { token: string; tenant: { slug: string } } }>('/public/signup', {
      method: 'POST', auth: false, tenant: false,
      body: {
        cafe_name: input.cafe_name.trim().slice(0, 80),
        slug: input.slug.trim().toLowerCase().slice(0, 30),
        owner_name: input.owner_name.trim().slice(0, 80),
        phone: toLatinDigits(input.phone).trim().slice(0, 20),
        password: input.password.slice(0, 200),
        code: toLatinDigits(input.code).trim().slice(0, 8),
        website: input.website,
      },
    });
    await startSession(data.token);
    await selectTenant(data.tenant.slug);
    slug = data.tenant.slug;
  } catch (error) {
    return fail(error);
  }

  redirect(`/dashboard?welcome=${encodeURIComponent(slug)}`);
}
