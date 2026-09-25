import type { NextConfig } from 'next';

// Scripts and styles need 'unsafe-inline' for the theme-flash-prevention snippet, the per-page
// JSON-LD blocks and Tailwind's own inline styles; there is no per-request nonce plumbing yet
// (proxy.ts only runs on a subset of routes). Images are 'https:' broadly because the media disk
// (local storage or S3-compatible object storage) is a deployment choice, not a fixed host.
// Media may also be served over plain http by the API itself (local development, or an
// intranet deployment): allow exactly that origin, never http: in general.
const origin = (url: string | undefined) => {
  try { return url ? new URL(url).origin : null; } catch { return null; }
};
const mediaOrigins = [...new Set([origin(process.env.MEDIA_ORIGIN), origin(process.env.API_URL)].filter((o): o is string => o !== null && o.startsWith('http:')))];
const isDev = process.env.NODE_ENV === 'development';

const csp = [
  "default-src 'self'",
  // React needs eval only in development (debug stacks); production never does.
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ''}`,
  "style-src 'self' 'unsafe-inline'",
  ['img-src', "'self'", 'data:', 'blob:', 'https:', ...mediaOrigins].join(' '),
  "font-src 'self'",
  "connect-src 'self'",
  "object-src 'none'",
  "base-uri 'none'",
  "form-action 'self'",
  "frame-ancestors 'none'",
].join('; ');

const securityHeaders = [
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'X-Frame-Options', value: 'DENY' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=(self)' },
  { key: 'Content-Security-Policy', value: csp },
];

const nextConfig: NextConfig = {
  // Workspace packages ship TypeScript source.
  transpilePackages: ['@cafe/ui', '@cafe/locale'],
  poweredByHeader: false,
  experimental: {
    // Logo uploads go through a Server Action (API limit is 1 MB).
    serverActions: { bodySizeLimit: '10mb' }, // story and cover photos (re-encoded server-side)
  },
  async headers() {
    return [{ source: '/:path*', headers: securityHeaders }];
  },
};

export default nextConfig;
