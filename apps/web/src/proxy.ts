import { NextResponse, type NextRequest } from 'next/server';

/**
 * Cheap optimistic gate: no session cookie → straight to login.
 * Real authorisation always happens in the API (token + membership + permission).
 */
export function proxy(request: NextRequest) {
  if (!request.cookies.has('cs_staff_token')) {
    const login = new URL('/login', request.url);
    login.searchParams.set('next', request.nextUrl.pathname);

    return NextResponse.redirect(login);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/dashboard/:path*', '/print/:path*', '/billing/:path*', '/ads/:path*', '/platform/:path*', '/select-tenant'],
};
