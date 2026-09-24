import { getStaffToken, getTenantSlug } from '@/lib/session';

const API_URL = process.env.API_URL ?? 'http://127.0.0.1:8000';

/** Passes the order-board version check (ETag / 304) through with the staff cookie. */
export async function GET(request: Request): Promise<Response> {
  const [token, tenant] = await Promise.all([getStaffToken(), getTenantSlug()]);
  if (!token || !tenant) return new Response(null, { status: 401 });

  const headers: Record<string, string> = { Accept: 'application/json', Authorization: `Bearer ${token}`, 'X-Tenant': tenant };
  const etag = request.headers.get('If-None-Match');
  if (etag) headers['If-None-Match'] = etag;

  try {
    const upstream = await fetch(`${API_URL}/api/v1/orders/live-version`, { headers, cache: 'no-store' });
    const out = new Headers({ 'Cache-Control': 'no-store' });
    const tag = upstream.headers.get('ETag');
    if (tag) out.set('ETag', tag);

    return upstream.status === 304 ? new Response(null, { status: 304, headers: out }) : new Response(upstream.body, { status: upstream.status, headers: out });
  } catch {
    return new Response(null, { status: 503 });
  }
}
