import type { MetadataRoute } from 'next';

/** Only café storefronts are public; staff screens, carts, accounts and tracking never are. */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{
      userAgent: '*',
      allow: '/s/',
      disallow: ['/dashboard', '/kds', '/login', '/select-tenant', '/s/*/cart', '/s/*/account', '/s/*/login', '/s/*/track/', '/s/*/pay/', '/s/*/t/'],
    }],
  };
}
