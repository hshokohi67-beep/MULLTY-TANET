'use server';

import { toLatinDigits } from '@cafe/locale';
import { ApiError, toFormState } from '@/lib/api';
import { clearCookie, readCookie, sf, writeCookie } from '@/lib/storefront';
import type { CartView, Club, Customer, CustomerAddress, QuoteLine } from '@/lib/storefront-types';
import type { FormState, Order } from '@/lib/types';

/**
 * Storefront Server Functions. The browser never holds a token: cart, customer and table tokens
 * stay in HttpOnly cookies scoped to /s/{tenant}, and only this server attaches them.
 */

export interface TableInfo { label: string; branch: string; branch_slug: string }

export interface StoreSession {
  cart: CartView | null;
  table: TableInfo | null;
  customer: { name: string | null; phone: string } | null;
}

type Result<T> = { ok: true; data: T; message?: string } | { ok: false; message: string; code?: string };

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

function fail(error: unknown): { ok: false; message: string; code?: string } {
  if (error instanceof ApiError) {
    const first = Object.values(error.errors)[0]?.[0];

    return { ok: false, message: first ?? error.message, code: error.code };
  }
  throw error;
}

async function tableInfo(tenant: string): Promise<TableInfo | null> {
  if (!(await readCookie(tenant, 'table'))) return null;
  try {
    return JSON.parse(decodeURIComponent((await readCookie(tenant, 'tableInfo')) ?? '')) as TableInfo;
  } catch {
    return null;
  }
}

async function currentCart(tenant: string): Promise<CartView | null> {
  if (!(await readCookie(tenant, 'cart'))) return null;
  try {
    return (await sf<{ data: CartView }>(tenant, '/public/cart', { cart: true })).data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 410)) {
      await clearCookie(tenant, 'cart');
      return null;
    }
    throw error;
  }
}

async function currentCustomer(tenant: string): Promise<Customer | null> {
  if (!(await readCookie(tenant, 'customer'))) return null;
  try {
    return (await sf<{ data: Customer }>(tenant, '/auth/customer/me')).data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      await clearCookie(tenant, 'customer');
      return null;
    }
    throw error;
  }
}

/** Everything personal the (statically rendered) menu needs, loaded once on the client. */
export async function loadSession(tenant: string): Promise<StoreSession> {
  const [cart, table, customer] = await Promise.all([currentCart(tenant), tableInfo(tenant), currentCustomer(tenant)]);

  return { cart, table, customer: customer ? { name: customer.name, phone: customer.phone } : null };
}

async function createCart(tenant: string, type: CartView['order_type'], branchId: string): Promise<CartView> {
  const body = type === 'qr_table' ? { order_type: type } : { order_type: type, branch_id: branchId };
  const { data } = await sf<{ data: CartView & { cart_token: string } }>(tenant, '/public/carts', { method: 'POST', body, table: type === 'qr_table' });
  await writeCookie(tenant, 'cart', data.cart_token);

  return data;
}

async function addLine(tenant: string, line: { variant_id: string; quantity: number; modifier_ids: string[]; note: string | null }): Promise<CartView> {
  return (await sf<{ data: CartView }>(tenant, '/public/cart/items', { method: 'POST', body: line, cart: true })).data;
}

export async function addToCart(tenant: string, input: { branchId: string; variantId: string; quantity: number; modifierIds: string[]; note?: string }): Promise<Result<CartView>> {
  if (!ULID.test(input.branchId) || !ULID.test(input.variantId) || !input.modifierIds.every((id) => ULID.test(id))) {
    return { ok: false, message: 'انتخاب نامعتبر است.' };
  }

  try {
    const table = await tableInfo(tenant);
    let cart = await currentCart(tenant);

    // A cart belongs to one branch and one order type; start a new one when the shopper moved on.
    if (!cart || (table ? cart.order_type !== 'qr_table' : cart.branch.id !== input.branchId || cart.order_type === 'qr_table')) {
      cart = await createCart(tenant, table ? 'qr_table' : 'takeaway', input.branchId);
    }

    const data = await addLine(tenant, {
      variant_id: input.variantId,
      quantity: Math.min(99, Math.max(1, Math.round(input.quantity))),
      modifier_ids: input.modifierIds,
      note: input.note?.trim() ? input.note.trim().slice(0, 200) : null,
    });

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function updateCartLine(tenant: string, ref: string, quantity: number): Promise<Result<CartView>> {
  if (!ULID.test(ref)) return { ok: false, message: 'آیتم نامعتبر است.' };
  try {
    const path = `/public/cart/items/${ref}`;
    const data = quantity <= 0
      ? (await sf<{ data: CartView }>(tenant, path, { method: 'DELETE', cart: true })).data
      : (await sf<{ data: CartView }>(tenant, path, { method: 'PATCH', body: { quantity: Math.min(99, Math.round(quantity)) }, cart: true })).data;

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

/** Order type is fixed per cart, so switching copies the lines into a fresh cart of the new type. */
export async function switchOrderType(tenant: string, type: 'takeaway' | 'delivery'): Promise<Result<CartView>> {
  try {
    const cart = await currentCart(tenant);
    if (!cart) return { ok: false, message: 'سبد خرید خالی است.' };
    if (cart.order_type === type) return { ok: true, data: cart };

    let next = await createCart(tenant, type, cart.branch.id);
    for (const line of cart.quote.lines as QuoteLine[]) {
      next = await addLine(tenant, { variant_id: line.variant_id, quantity: line.quantity, modifier_ids: line.modifiers.map((m) => m.modifier_id), note: line.note });
    }

    return { ok: true, data: next };
  } catch (error) {
    return fail(error);
  }
}

export async function quoteCart(tenant: string, options: { coupon?: string; addressId?: string; scheduledFor?: string }): Promise<Result<CartView>> {
  const query = new URLSearchParams();
  if (options.coupon) query.set('coupon_code', toLatinDigits(options.coupon.trim()).slice(0, 40));
  if (options.addressId && ULID.test(options.addressId)) query.set('address_id', options.addressId);
  if (options.scheduledFor && !Number.isNaN(Date.parse(options.scheduledFor))) query.set('scheduled_for', options.scheduledFor);

  try {
    return { ok: true, data: (await sf<{ data: CartView }>(tenant, `/public/cart?${query}`, { cart: true })).data };
  } catch (error) {
    return fail(error);
  }
}

export async function reorder(tenant: string, orderId: string, branchId: string): Promise<Result<{ skipped: string[] }>> {
  if (!ULID.test(orderId) || !ULID.test(branchId)) return { ok: false, message: 'سفارش نامعتبر است.' };
  try {
    const cart = await currentCart(tenant);
    if (!cart || cart.order_type === 'qr_table' || cart.branch.id !== branchId) await createCart(tenant, 'takeaway', branchId);
    const { data } = await sf<{ data: CartView & { skipped: string[] } }>(tenant, '/public/cart/reorder', { method: 'POST', body: { order_id: orderId }, cart: true });

    return { ok: true, data: { skipped: data.skipped } };
  } catch (error) {
    return fail(error);
  }
}

export interface CheckoutInput {
  key: string;
  payment: 'cash' | 'online';
  useWallet: boolean;
  coupon?: string;
  addressId?: string;
  scheduledFor?: string;
  note?: string;
  contactName?: string;
}

/**
 * Places the order, then (optionally) pays from the club wallet, then (optionally) sends the rest
 * to the gateway. Each step is idempotent on the same key, so a retry never double-charges.
 * A wallet that covers everything releases an online-intent order to the kitchen by itself.
 */
export async function placeOrder(tenant: string, input: CheckoutInput): Promise<Result<{ redirect: string }>> {
  if (!/^[0-9a-f-]{36}$/i.test(input.key)) return { ok: false, message: 'درخواست نامعتبر است؛ صفحه را تازه کنید.' };

  let order: Order;
  let token: string;
  try {
    const res = await sf<{ data: Order; tracking_token: string }>(tenant, '/public/checkout', {
      method: 'POST',
      cart: true,
      headers: { 'Idempotency-Key': input.key },
      body: {
        payment_method: input.payment,
        coupon_code: input.coupon ? toLatinDigits(input.coupon.trim()) : null,
        address_id: input.addressId && ULID.test(input.addressId) ? input.addressId : null,
        scheduled_for: input.scheduledFor || null,
        note: input.note?.trim() ? input.note.trim().slice(0, 300) : null,
        contact_name: input.contactName?.trim() ? input.contactName.trim().slice(0, 120) : null,
      },
    });
    order = res.data;
    token = res.tracking_token;
  } catch (error) {
    return fail(error);
  }

  await clearCookie(tenant, 'cart');
  const track = `/s/${tenant}/track/${order.id}#t=${encodeURIComponent(token)}`;
  let remaining = order.remaining_due;
  let message: string | undefined;

  if (input.useWallet && remaining > 0) {
    try {
      const res = await sf<{ order: { remaining_due: number } }>(tenant, `/customer/orders/${order.id}/wallet-payment`, {
        method: 'POST',
        headers: { 'Idempotency-Key': `${input.key}-wallet` },
      });
      remaining = res.order.remaining_due;
    } catch (error) {
      // The order is placed either way; the rest is paid online or at the counter.
      message = error instanceof ApiError ? error.message : undefined;
    }
  }

  if (input.payment === 'online' && remaining > 0) {
    try {
      const { data } = await sf<{ data: { redirect_url: string } }>(tenant, `/public/orders/${order.id}/pay`, {
        method: 'POST',
        customer: false,
        headers: { 'X-Order-Token': token },
      });

      return { ok: true, data: { redirect: data.redirect_url } };
    } catch (error) {
      return { ok: true, data: { redirect: track }, message: error instanceof ApiError ? error.message : message };
    }
  }

  return { ok: true, data: { redirect: track }, message };
}

export async function tableRequest(tenant: string, type: 'call_waiter' | 'request_bill'): Promise<Result<null>> {
  try {
    const res = await sf<{ message: string }>(tenant, '/public/tables/requests', { method: 'POST', body: { type }, table: true, customer: false });

    return { ok: true, data: null, message: res.message };
  } catch (error) {
    if (error instanceof ApiError && error.status === 410) await clearCookie(tenant, 'table');
    return fail(error);
  }
}

export async function leaveTable(tenant: string): Promise<void> {
  await clearCookie(tenant, 'table');
  await clearCookie(tenant, 'tableInfo');
  await clearCookie(tenant, 'cart');
}

/* ------------------------------------------------------------------ customer login */

export async function requestOtp(tenant: string, phone: string): Promise<Result<{ resend_after: number }>> {
  try {
    const res = await sf<{ message: string; resend_after: number }>(tenant, '/auth/customer/otp/request', {
      method: 'POST',
      customer: false,
      body: { phone: toLatinDigits(phone).trim() },
    });

    return { ok: true, data: { resend_after: res.resend_after }, message: res.message };
  } catch (error) {
    return fail(error);
  }
}

export async function verifyOtp(tenant: string, phone: string, code: string): Promise<Result<{ is_new: boolean }>> {
  try {
    const res = await sf<{ token: string; is_new: boolean }>(tenant, '/auth/customer/otp/verify', {
      method: 'POST',
      customer: false,
      body: { phone: toLatinDigits(phone).trim(), code: toLatinDigits(code).trim(), device_name: 'storefront' },
    });
    await writeCookie(tenant, 'customer', res.token);

    return { ok: true, data: { is_new: res.is_new } };
  } catch (error) {
    return fail(error);
  }
}

export async function logoutCustomer(tenant: string): Promise<void> {
  try {
    await sf(tenant, '/auth/customer/logout', { method: 'POST' });
  } catch {
    // The token may already be gone; the cookie is cleared either way.
  }
  await clearCookie(tenant, 'customer');
}

/* ------------------------------------------------------------------ account */

function field(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

export async function updateProfile(tenant: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const month = field(formData, 'birth_month');
  const day = field(formData, 'birth_day');
  try {
    await sf(tenant, '/customer/profile', {
      method: 'PATCH',
      body: {
        name: field(formData, 'name'),
        ...(month && day ? { birth_month: Number(toLatinDigits(month)), birth_day: Number(toLatinDigits(day)) } : {}),
        marketing_opt_in: formData.get('marketing_opt_in') === 'on',
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  return { ok: true, message: 'ذخیره شد.' };
}

export async function saveAddress(tenant: string, addressId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const lat = field(formData, 'latitude');
  const lng = field(formData, 'longitude');
  const body = {
    title: field(formData, 'title'),
    recipient_name: field(formData, 'recipient_name'),
    recipient_phone: field(formData, 'recipient_phone'),
    city: field(formData, 'city'),
    district: field(formData, 'district'),
    address: field(formData, 'address'),
    postal_code: field(formData, 'postal_code'),
    building_number: field(formData, 'building_number'),
    floor: field(formData, 'floor'),
    unit: field(formData, 'unit'),
    notes: field(formData, 'notes'),
    latitude: lat ? Number(lat) : null,
    longitude: lng ? Number(lng) : null,
    is_default: formData.get('is_default') === 'on',
  };

  try {
    if (addressId && ULID.test(addressId)) {
      await sf(tenant, `/customer/addresses/${addressId}`, { method: 'PUT', body });
    } else {
      await sf(tenant, '/customer/addresses', { method: 'POST', body });
    }
  } catch (error) {
    return toFormState(error);
  }

  return { ok: true, message: 'آدرس ذخیره شد.' };
}

export async function deleteAddress(tenant: string, addressId: string): Promise<Result<null>> {
  if (!ULID.test(addressId)) return { ok: false, message: 'آدرس نامعتبر است.' };
  try {
    await sf(tenant, `/customer/addresses/${addressId}`, { method: 'DELETE' });
    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}

export async function listAddresses(tenant: string): Promise<CustomerAddress[]> {
  try {
    return (await sf<{ data: CustomerAddress[] }>(tenant, '/customer/addresses')).data;
  } catch {
    return [];
  }
}

export interface ZoneCheck { zone_name: string; distance_m: number; fee: number; free_delivery_min: number | null; min_order: number; eta_minutes: number | null }

export async function checkDelivery(tenant: string, branchId: string, latitude: number, longitude: number): Promise<Result<ZoneCheck>> {
  if (!ULID.test(branchId) || !Number.isFinite(latitude) || !Number.isFinite(longitude)) return { ok: false, message: 'موقعیت نامعتبر است.' };
  try {
    return { ok: true, data: (await sf<{ data: ZoneCheck }>(tenant, '/public/delivery/check', { method: 'POST', customer: false, body: { branch_id: branchId, latitude, longitude } })).data };
  } catch (error) {
    return fail(error);
  }
}

export async function redeemPoints(tenant: string, points: number): Promise<Result<Club>> {
  try {
    const res = await sf<{ data: Club; message: string }>(tenant, '/customer/points/redeem', { method: 'POST', body: { points } });

    return { ok: true, data: res.data, message: res.message };
  } catch (error) {
    return fail(error);
  }
}

export async function applyReferral(tenant: string, code: string): Promise<Result<null>> {
  try {
    const res = await sf<{ message: string }>(tenant, '/customer/referral', { method: 'POST', body: { code: toLatinDigits(code).trim() } });

    return { ok: true, data: null, message: res.message };
  } catch (error) {
    return fail(error);
  }
}

/** Anonymous story view/click counting (deduplicated per visitor per day by the API). */
export async function storyEvent(tenant: string, storyId: string, kind: 'seen' | 'click'): Promise<void> {
  if (!ULID.test(storyId)) return;
  try {
    await sf(tenant, `/public/stories/${storyId}/${kind}`, { method: 'POST', customer: false });
  } catch {
    // Counting must never disturb the viewer.
  }
}

export interface PreorderSlots {
  open_now: boolean;
  lead_minutes: number;
  slot_minutes: number;
  days: { date: string; label: string; slots: { start: string; label: string; available: boolean }[] }[];
}

/** Pre-order days and time slots of a branch (opening hours, lead time and capacity applied by the API). */
export async function loadPreorderSlots(tenant: string, branchId: string): Promise<PreorderSlots | null> {
  if (!ULID.test(branchId)) return null;
  try {
    return (await sf<{ data: PreorderSlots }>(tenant, `/public/preorder-slots?branch_id=${branchId}`, { customer: false })).data;
  } catch {
    return null;
  }
}
