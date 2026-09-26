import { NextResponse, type NextRequest } from 'next/server';
import { api } from '@/lib/api';
import { cookiePathFor, storeUrl, TENANT_SLUG } from '@/lib/storefront';

interface Joined { session_token: string; table: { label: string }; branch: { name: string; slug: string } }

/**
 * A printed table QR lands here. We join the table session, keep its token in an HttpOnly cookie
 * and redirect at once to the menu, so the QR token never stays in the address bar or history.
 */
export async function GET(request: NextRequest, { params }: RouteContext<'/s/[tenant]/t/[token]'>) {
  const { tenant, token } = await params;
  if (!TENANT_SLUG.test(tenant) || token.length < 20 || token.length > 120) {
    return NextResponse.redirect(storeUrl(tenant, '/menu?qr=invalid'), 303);
  }

  let joined: Joined;
  try {
    const ip = request.headers.get('x-forwarded-for')?.split(',')[0]?.trim();
    joined = (await api<{ data: Joined }>('/public/tables/session', {
      method: 'POST',
      auth: false,
      tenant,
      body: { qr_token: token },
      headers: ip ? { 'X-Forwarded-For': ip } : {},
    })).data;
  } catch {
    return NextResponse.redirect(storeUrl(tenant, '/menu?qr=invalid'), 303);
  }

  const response = NextResponse.redirect(storeUrl(tenant, `/menu?branch=${encodeURIComponent(joined.branch.slug)}`), 303);
  // On the café's own subdomain the cookies cover the whole host (see lib/storefront).
  const path = cookiePathFor(tenant, request.headers.get('x-forwarded-host') ?? request.headers.get('host'));
  const cookie = { httpOnly: true, secure: process.env.NODE_ENV === 'production', sameSite: 'lax' as const, path, maxAge: 4 * 3600 };
  response.cookies.set('cs_table', joined.session_token, cookie);
  response.cookies.set('cs_table_info', encodeURIComponent(JSON.stringify({ label: joined.table.label, branch: joined.branch.name, branch_slug: joined.branch.slug })), cookie);
  // A cart from before (takeaway, or another table) doesn't belong to this table.
  response.cookies.set('cs_cart', '', { path, maxAge: 0 });
  response.headers.set('Referrer-Policy', 'no-referrer');
  response.headers.set('Cache-Control', 'no-store');

  return response;
}
