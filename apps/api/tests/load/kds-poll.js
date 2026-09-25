// The kitchen screen's poll. Realistic load here is a handful of tablets per branch, not a
// crowd — this checks latency under a busy kitchen (many orders), not concurrency.
// Requires a paired device token (pair a tablet on the target tenant first, or use
// KitchenDevices::pair directly against a seeded pairing code).
//
// Run: k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo \
//        -e KDS_DEVICE_TOKEN=<paste after pairing> kds-poll.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8765';
const TENANT = __ENV.TENANT_SLUG;
const DEVICE_TOKEN = __ENV.KDS_DEVICE_TOKEN;

if (!TENANT || !DEVICE_TOKEN) {
  throw new Error('Set TENANT_SLUG and KDS_DEVICE_TOKEN (pair a tablet on the target tenant first).');
}

export const options = {
  vus: __ENV.VUS ? Number(__ENV.VUS) : 10,
  duration: __ENV.DURATION || '3m',
  thresholds: {
    http_req_duration: ['p(95)<250'], // the kitchen screen has to stay snappy under a rush
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  const res = http.get(`${BASE_URL}/api/v1/kds/board`, {
    headers: { 'X-Tenant': TENANT, Authorization: `Bearer ${DEVICE_TOKEN}`, Accept: 'application/json' },
  });
  check(res, { 'board ok': (r) => r.status === 200 });
  sleep(2);
}
