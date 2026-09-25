import type { MetadataRoute } from 'next';
import { searchStores } from '@/lib/marketplace';

export const revalidate = 3600;

/** The marketplace home and every listed café's profile (storefronts publish their own sitemaps). */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = (process.env.STOREFRONT_URL ?? 'http://localhost:3765').replace(/\/$/, '');
  const stores = new Set<string>();

  try {
    for (let page = 1, last = 1; page <= Math.min(last, 40); page++) {
      const res = await searchStores(new URLSearchParams({ page: String(page) }));
      res.data.forEach((s) => stores.add(s.store));
      last = res.meta.last_page;
    }
  } catch {
    // The API being down must not break the sitemap: publish what we have.
  }

  return [
    { url: `${base}/explore`, changeFrequency: 'daily', priority: 1 },
    ...[...stores].map((slug) => ({ url: `${base}/explore/${slug}`, changeFrequency: 'weekly' as const, priority: 0.7 })),
  ];
}
