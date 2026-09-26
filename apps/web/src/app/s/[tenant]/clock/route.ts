/**
 * The server's clock, so offer countdowns don't depend on a visitor's wrong phone clock
 * (lib/clock). No API call, no personal data, never cached.
 */
export const dynamic = 'force-dynamic';

export function GET() {
  return Response.json({ now: Date.now() }, { headers: { 'Cache-Control': 'no-store' } });
}
