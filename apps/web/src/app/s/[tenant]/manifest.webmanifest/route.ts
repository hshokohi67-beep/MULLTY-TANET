import { NextResponse } from 'next/server';
import { brandTokens } from '@cafe/ui';
import { getStorefront } from '@/lib/storefront';

export const revalidate = 300;

/** Per-café PWA manifest: "add to home screen" opens this café's menu, in its colours. */
export async function GET(_request: Request, { params }: RouteContext<'/s/[tenant]/manifest.webmanifest'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) return new NextResponse(null, { status: 404 });

  const logo = store.branding?.logo_url;

  return NextResponse.json({
    name: store.name,
    short_name: store.name.slice(0, 12),
    description: store.branding?.seo_description ?? `منوی آنلاین ${store.name}`,
    lang: 'fa',
    dir: 'rtl',
    start_url: `/s/${tenant}`,
    scope: `/s/${tenant}`,
    display: 'standalone',
    background_color: '#f6f5f1',
    theme_color: brandTokens(store.branding?.primary_color)?.brand ?? '#0e7c6b',
    icons: logo ? [{ src: logo, sizes: 'any', purpose: 'any' }] : [{ src: '/favicon.ico', sizes: '48x48', type: 'image/x-icon' }],
  }, { headers: { 'Content-Type': 'application/manifest+json' } });
}
