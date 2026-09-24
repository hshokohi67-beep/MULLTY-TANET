import { ApiError } from '@/lib/api';
import { kdsFetch } from '@/lib/kds';

/**
 * The screen polls this every few seconds. ETag / If-None-Match pass straight through, so an
 * unchanged board costs a 304 with no body all the way to the API.
 */
export async function GET(request: Request): Promise<Response> {
  const query = new URL(request.url).searchParams.toString();

  try {
    const upstream = await kdsFetch(`/kds/board${query ? `?${query}` : ''}`, { etag: request.headers.get('If-None-Match') });
    const headers = new Headers({ 'Cache-Control': 'no-store' });
    const etag = upstream.headers.get('ETag');
    if (etag) headers.set('ETag', etag);

    if (upstream.status === 304) return new Response(null, { status: 304, headers });

    headers.set('Content-Type', 'application/json');

    return new Response(upstream.body, { status: upstream.status, headers });
  } catch (error) {
    const status = error instanceof ApiError ? error.status : 503;

    return Response.json({ message: error instanceof ApiError ? error.message : 'ارتباط برقرار نشد.' }, { status });
  }
}
