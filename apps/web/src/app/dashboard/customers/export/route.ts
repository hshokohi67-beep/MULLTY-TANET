import { apiRaw } from '@/lib/api';

/**
 * Streams the API's CSV export to the browser. The staff token never leaves the server;
 * the API checks customers.export and records the export in the audit log.
 */
export async function GET(request: Request): Promise<Response> {
  const query = new URL(request.url).searchParams.toString();
  const upstream = await apiRaw(`/customers/export${query ? `?${query}` : ''}`);

  if (!upstream.ok || upstream.body === null) {
    return new Response('دسترسی به خروجی مشتریان ممکن نیست.', { status: upstream.status === 403 ? 403 : 502, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }

  const date = new Date().toISOString().slice(0, 10);

  return new Response(upstream.body, {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': `attachment; filename="customers-${date}.csv"`,
      'Cache-Control': 'no-store',
    },
  });
}
