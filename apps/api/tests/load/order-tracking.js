// The order-tracking page's poll, once an order exists. setup() places one real order (same path
// as checkout-flow.js) and every VU then polls its status, mirroring the storefront's behaviour.
//
// Run: k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo \
//        -e TABLE_QR_TOKEN=<paste from the dashboard> order-tracking.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8765';
const TENANT = __ENV.TENANT_SLUG;
const QR_TOKEN = __ENV.TABLE_QR_TOKEN;

if (!TENANT || !QR_TOKEN) {
  throw new Error('Set TENANT_SLUG and TABLE_QR_TOKEN (a seeded table QR token on the target tenant).');
}

export const options = {
  vus: __ENV.VUS ? Number(__ENV.VUS) : 30,
  duration: __ENV.DURATION || '2m',
  thresholds: {
    http_req_duration: ['p(95)<300'],
    http_req_failed: ['rate<0.01'],
  },
};

const headers = { 'X-Tenant': TENANT, 'Content-Type': 'application/json', Accept: 'application/json' };

export function setup() {
  const session = http.post(`${BASE_URL}/api/v1/public/tables/session`, JSON.stringify({ qr_token: QR_TOKEN }), { headers });
  const sessionToken = session.json('data.session_token');
  const cartHeaders = { ...headers, 'X-Table-Session': sessionToken };

  const menu = http.get(`${BASE_URL}/api/v1/public/menu`, { headers });
  const variantId = menu.json('data.products.0.variants.0.id');
  if (!variantId) {
    throw new Error('The seeded tenant has no orderable product; add at least one before running this script.');
  }

  const cart = http.post(`${BASE_URL}/api/v1/public/carts`, JSON.stringify({ order_type: 'qr_table' }), { headers: cartHeaders });
  const cartToken = cart.json('data.cart_token');
  const itemHeaders = { ...cartHeaders, 'X-Cart-Token': cartToken };
  http.post(`${BASE_URL}/api/v1/public/cart/items`, JSON.stringify({ variant_id: variantId, quantity: 1 }), { headers: itemHeaders });

  const checkout = http.post(`${BASE_URL}/api/v1/public/checkout`, JSON.stringify({ payment_method: 'cash' }), {
    headers: { ...itemHeaders, 'Idempotency-Key': `k6-tracking-setup-${Date.now()}` },
  });

  return { orderId: checkout.json('data.id'), trackingToken: checkout.json('tracking_token') };
}

export default function (data) {
  const res = http.get(`${BASE_URL}/api/v1/public/orders/${data.orderId}?token=${data.trackingToken}`, { headers });
  check(res, { 'tracking ok': (r) => r.status === 200 });
  sleep(5); // the real tracking page's poll cadence
}
