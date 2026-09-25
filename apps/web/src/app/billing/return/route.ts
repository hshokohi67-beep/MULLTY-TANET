import { api, ApiError } from '@/lib/api';

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

/**
 * Where the payment gateway sends the owner back. Verifies server-to-server through the API
 * (safe to repeat) and lands on the subscription page with the outcome.
 */
export async function GET(request: Request): Promise<Response> {
  const url = new URL(request.url);
  const invoice = url.searchParams.get('invoice') ?? '';
  const authority = url.searchParams.get('Authority') ?? '';
  // Relative Location: the server may see its own host (e.g. localhost) rather than the one the browser used.
  const back = (result: string) => new Response(null, { status: 303, headers: { Location: `/dashboard/billing?payment=${result}` } });

  if (!ULID.test(invoice) || authority === '' || authority.length > 64) return back('failed');
  if (url.searchParams.get('Status') !== 'OK') return back('cancelled');

  try {
    const { data } = await api<{ data: { paid: boolean } }>(`/billing/invoices/${invoice}/verify`, { method: 'POST', body: { authority } });

    return back(data.paid ? 'ok' : 'failed');
  } catch (error) {
    if (error instanceof ApiError) return back('failed');
    throw error;
  }
}
