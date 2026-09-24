import { getMenu, getStorefront, storeUrl } from '@/lib/storefront';

export const revalidate = 3600;

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** One sitemap per café: the menu (per branch) and every product page, from the cached public menu. */
export async function GET(_request: Request, { params }: RouteContext<'/s/[tenant]/sitemap.xml'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) return new Response(null, { status: 404 });

  const menu = store.branches[0] ? await getMenu(tenant, store.branches[0].slug) : null;
  const lastmod = menu?.generated_at ?? new Date().toISOString();
  const urls = [
    storeUrl(tenant),
    ...store.branches.slice(1).map((b) => storeUrl(tenant, `?branch=${encodeURIComponent(b.slug)}`)),
    ...(menu?.products ?? []).map((p) => storeUrl(tenant, `/p/${encodeURIComponent(p.slug)}`)),
  ];

  const body = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${
    urls.map((u) => `  <url><loc>${esc(u)}</loc><lastmod>${lastmod}</lastmod></url>`).join('\n')
  }\n</urlset>\n`;

  return new Response(body, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
}
