import type { MetadataRoute } from 'next';

/** Only the marketplace and café storefronts are public; staff screens, carts, accounts and tracking never are. */
export default function robots(): MetadataRoute.Robots {
  const base = (process.env.STOREFRONT_URL ?? 'http://localhost:3765').replace(/\/$/, '');

  return {
    rules: [{
      userAgent: '*',
      allow: ['/s/', '/explore'],
      disallow: ['/dashboard', '/platform', '/print', '/billing', '/kds', '/login', '/select-tenant', '/s/*/cart', '/s/*/account', '/s/*/login', '/s/*/track/', '/s/*/pay/', '/s/*/t/'],
    }],
    sitemap: `${base}/sitemap.xml`,
  };
}
