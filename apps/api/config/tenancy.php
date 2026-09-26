<?php

return [
    /*
     | How the API learns which tenant a request is for:
     |  1. X-Tenant header (tenant slug), used by the dashboard and by SSR calls from Next.js.
     |  2. X-Tenant-Domain header, which Next.js sets to the visitor's original Host on storefront requests.
     | The tenant slug/domain is public information. Authorisation always comes from
     | membership (staff) or token-to-tenant binding (customers), never from the header alone.
     */
    'header' => 'X-Tenant',
    'domain_header' => 'X-Tenant-Domain',

    // Base domain for automatic subdomains: {slug}.{subdomain_base}
    'subdomain_base' => env('TENANT_SUBDOMAIN_BASE', 'menu.localhost'),

    // Each café's storefront on its own subdomain ({slug}.{subdomain_base}); the web proxy serves it.
    // Payment callbacks then return to that host, where the visitor's cookies live.
    'storefront_subdomains' => (bool) env('STOREFRONT_SUBDOMAINS', false),
    'storefront_scheme' => env('STOREFRONT_SCHEME', 'https'),

    // Slugs a tenant can never take (they collide with platform hosts/routes).
    'reserved_slugs' => ['www', 'api', 'admin', 'app', 'dashboard', 'panel', 'static', 'cdn', 'mail', 'help', 'support', 'status', 'marketplace'],
];
