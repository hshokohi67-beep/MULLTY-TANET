import { apiRaw } from '@/lib/api';

/** The blank menu template (.xlsx) from the API; the staff token never leaves the server. */
export async function GET(): Promise<Response> {
  const upstream = await apiRaw('/catalog/import/template');
  if (!upstream.ok || upstream.body === null) {
    return new Response('دریافت فایل نمونه ممکن نشد.', { status: upstream.status === 403 ? 403 : 502, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }

  return new Response(upstream.body, {
    headers: {
      'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'Content-Disposition': 'attachment; filename="menu-template.xlsx"',
      'Cache-Control': 'no-store',
    },
  });
}
