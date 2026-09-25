// Highest-traffic path: a visitor scanning a table QR or opening a café's storefront link and
// reading the menu. No auth, no writes — this is the floor every other flow builds on.
//
// Run: k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo menu-browse.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8765';
const TENANT = __ENV.TENANT_SLUG || 'cafe-demo';

export const options = {
  stages: [
    { duration: '30s', target: 50 },
    { duration: '2m', target: 50 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<400'],
    http_req_failed: ['rate<0.01'],
  },
};

const headers = { 'X-Tenant': TENANT, Accept: 'application/json' };

export default function () {
  const shell = http.get(`${BASE_URL}/api/v1/public/storefront`, { headers });
  check(shell, { 'storefront ok': (r) => r.status === 200 });

  const menu = http.get(`${BASE_URL}/api/v1/public/menu`, { headers });
  check(menu, {
    'menu ok': (r) => r.status === 200,
    'has products': (r) => Array.isArray(r.json('data.products')),
  });

  sleep(Math.random() * 3 + 1); // a person reading a menu, not a bot hammering it
}
