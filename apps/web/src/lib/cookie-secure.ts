/**
 * Whether our HttpOnly cookies are marked Secure. Always in production (the site is https), unless
 * COOKIE_SECURE=false: only for testing a production build over plain http on a local network
 * (e.g. http://192.168.x.x from a phone), where browsers refuse to store Secure cookies.
 */
export const COOKIE_SECURE = process.env.COOKIE_SECURE === 'false' ? false : process.env.NODE_ENV === 'production';
