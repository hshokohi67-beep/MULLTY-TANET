import type { Metadata } from 'next';
import { OrderTracker } from './OrderTracker';

export const metadata: Metadata = { title: 'پیگیری سفارش', robots: { index: false, follow: false }, referrer: 'no-referrer' };

/**
 * Live order status for the customer. The tracking token is in the URL fragment (#t=…), which
 * browsers never send to servers or put in Referer headers; the tracker reads it on the client.
 * Phase 7 folds this into the full storefront.
 */
export default async function TrackOrderPage({ params }: PageProps<'/s/[tenant]/track/[order]'>) {
  const { tenant, order } = await params;

  return (
    <div className="mx-auto flex min-h-[60dvh] max-w-md flex-col gap-6 py-8">
      <OrderTracker tenant={tenant} orderId={order} />
    </div>
  );
}
