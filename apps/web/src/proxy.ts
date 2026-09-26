import { NextResponse, type NextRequest } from 'next/server';
import { slugFromHost } from '@/lib/store-links';

/** Staff areas: no session cookie → straight to login (real authorisation is always the API's). */
const GATED = ['/dashboard', '/print', '/billing', '/ads', '/platform', '/select-tenant'];

/** Never rewritten on a café subdomain. */
const PASS = ['/_next', '/fonts', '/favicon.ico', '/robots.txt'];

/**
 * 1. Café subdomains ({slug}.{NEXT_PUBLIC_STORE_BASE_DOMAIN}): "/x" is served by /s/{slug}/x.
 *    Links that already carry /s/{slug} pass through; another café's /s/{other} is refused.
 *    The slug travels to the app in `x-store-host` (cookie scoping in lib/storefront).
 * 2. Cheap optimistic gate for staff areas.
 */
export function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;
  const slug = slugFromHost(request.headers.get('host'));

  if (slug !== null && !PASS.some((p) => pathname === p || pathname.startsWith(`${p}/`))) {
    const headers = new Headers(request.headers);
    headers.set('x-store-host', slug);
    if (pathname.startsWith('/s/')) {
      const other = pathname.split('/')[2];
      if (other !== slug) return new NextResponse(null, { status: 404 });

      return NextResponse.next({ request: { headers } });
    }
    const url = request.nextUrl.clone();
    url.pathname = `/s/${slug}${pathname === '/' ? '' : pathname}`;
    url.search = search;

    return NextResponse.rewrite(url, { request: { headers } });
  }

  if (GATED.some((p) => pathname === p || pathname.startsWith(`${p}/`)) && !request.cookies.has('cs_staff_token')) {
    const login = new URL('/login', request.url);
    login.searchParams.set('next', pathname);

    return NextResponse.redirect(login);
  }

  // The café-host header is ours alone: never trust one a client sent on the main domain.
  if (request.headers.has('x-store-host')) {
    const headers = new Headers(request.headers);
    headers.delete('x-store-host');

    return NextResponse.next({ request: { headers } });
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico|fonts/).*)'],
};
