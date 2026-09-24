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

    // Slugs a tenant can never take (they collide with platform hosts/routes).
    'reserved_slugs' => ['www', 'api', 'admin', 'app', 'dashboard', 'panel', 'static', 'cdn', 'mail', 'help', 'support', 'status', 'marketplace'],
];
