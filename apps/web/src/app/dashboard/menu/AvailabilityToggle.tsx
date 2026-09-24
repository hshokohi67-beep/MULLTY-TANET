'use client';

import { useTransition } from 'react';
import { Button } from '@cafe/ui';
import { setAvailability } from '@/app/actions/catalog';

/** One tap for the busiest moment of the day: "we ran out". */
export function AvailabilityToggle({ productId, branchId, soldOut, name }: { productId: string; branchId: string; soldOut: boolean; name: string }) {
  const [pending, start] = useTransition();

  return (
    <Button
      size="sm"
      variant={soldOut ? 'secondary' : 'ghost'}
      loading={pending}
      aria-pressed={soldOut}
      aria-label={soldOut ? `«${name}» دوباره موجود شد` : `«${name}» تمام شد`}
      onClick={() => start(() => setAvailability(productId, branchId, soldOut ? 'available' : 'sold_out'))}
    >
      {soldOut ? 'موجود شد' : 'تمام شد'}
    </Button>
  );
}
