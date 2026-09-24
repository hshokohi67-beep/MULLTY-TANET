'use server';

import { api, ApiError } from '@/lib/api';
import type { Order } from '@/lib/types';

/**
 * Customer order tracking. The tracking token travels from the page's URL fragment (never sent
 * to any server by the browser) to here in a POST body, and on to the API in a header.
 */
export async function trackOrder(tenant: string, orderId: string, token: string): Promise<{ order: Order | null; error?: string }> {
  if (!/^[a-z0-9-]{2,60}$/.test(tenant) || !/^[0-9a-z]{26}$/i.test(orderId) || token.length < 20 || token.length > 100) {
    return { order: null, error: 'لینک پیگیری کامل نیست.' };
  }

  try {
    const { data } = await api<{ data: Order }>(`/public/orders/${orderId}`, { auth: false, tenant, headers: { 'X-Order-Token': token } });

    return { order: data };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return { order: null, error: 'سفارشی با این لینک پیدا نشد.' };

    return { order: null, error: 'ارتباط برقرار نشد؛ دوباره تلاش می‌کنیم…' };
  }
}
