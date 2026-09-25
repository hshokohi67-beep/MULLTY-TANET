import { apiRaw } from '@/lib/api';

const TYPES: Record<string, string> = {
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  csv: 'text/csv; charset=utf-8',
};

/** Streams a report export from the API. The staff token never reaches the browser. */
export async function GET(request: Request): Promise<Response> {
  const params = new URL(request.url).searchParams;
  const format = params.get('format') === 'csv' ? 'csv' : 'xlsx';
  const report = params.get('report') ?? 'summary';
  const upstream = await apiRaw(`/reports/export?${params.toString()}`);

  if (!upstream.ok || upstream.body === null) {
    return new Response('دریافت خروجی گزارش ممکن نیست.', { status: upstream.status === 403 ? 403 : 502, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }

  const name = `report-${report.replace(/[^a-z]/g, '')}-${params.get('from') ?? ''}_${params.get('to') ?? ''}.${format}`.replace(/[^\w.-]/g, '');

  return new Response(upstream.body, {
    headers: { 'Content-Type': TYPES[format], 'Content-Disposition': `attachment; filename="${name}"`, 'Cache-Control': 'no-store' },
  });
}
