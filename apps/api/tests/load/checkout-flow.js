// The end-to-end QR-table order: join the table, read the menu, build a cart, check out with
// cash. Requires a seeded table QR token on the target tenant (staff dashboard -> Tables -> QR),
// since tokens are opaque and rotate; there is no way to mint one without staff auth.
//
// Run: k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo \
//        -e TABLE_QR_TOKEN=<paste from the dashboard> checkout-flow.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8765';
const TENANT = __ENV.TENANT_SLUG;
const QR_TOKEN = __ENV.TABLE_QR_TOKEN;

if (!TENANT || !QR_TOKEN) {
  throw new Error('Set TENANT_SLUG and TABLE_QR_TOKEN (a seeded table QR token on the target tenant).');
}

const checkoutSuccess = new Rate('checkout_success');

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '2m', target: 10 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<800'],
    http_req_failed: ['rate<0.02'],
    checkout_success: ['rate>0.98'],
  },
};

const headers = { 'X-Tenant': TENANT, 'Content-Type': 'application/json', Accept: 'application/json' };

export default function () {
  const session = http.post(`${BASE_URL}/api/v1/public/tables/session`, JSON.stringify({ qr_token: QR_TOKEN }), { headers });
  if (!check(session, { 'table session started': (r) => r.status === 200 })) {
    sleep(1);
    return;
  }
  const sessionToken = session.json('data.session_token');
  const cartHeaders = { ...headers, 'X-Table-Session': sessionToken };

  const menu = http.get(`${BASE_URL}/api/v1/public/menu`, { headers });
  const variantId = menu.json('data.products.0.variants.0.id');
  if (!variantId) {
    sleep(1);
    return;
  }

  const cart = http.post(`${BASE_URL}/api/v1/public/carts`, JSON.stringify({ order_type: 'qr_table' }), { headers: cartHeaders });
  const cartToken = cart.json('data.cart_token');
  const itemHeaders = { ...cartHeaders, 'X-Cart-Token': cartToken };

  http.post(`${BASE_URL}/api/v1/public/cart/items`, JSON.stringify({ variant_id: variantId, quantity: 1 }), { headers: itemHeaders });

  const checkout = http.post(`${BASE_URL}/api/v1/public/checkout`, JSON.stringify({ payment_method: 'cash' }), {
    headers: { ...itemHeaders, 'Idempotency-Key': `k6-${__VU}-${__ITER}-${Date.now()}` },
  });
  checkoutSuccess.add(checkout.status === 201);
  check(checkout, { 'order placed': (r) => r.status === 201 });

  sleep(2);
}
