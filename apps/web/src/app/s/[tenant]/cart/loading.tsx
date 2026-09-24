import { Skeleton } from '@cafe/ui';

export default function Loading() {
  return (
    <div className="mx-auto grid max-w-5xl gap-6 pt-5 lg:grid-cols-[1fr_22rem]" aria-busy="true" aria-label="در حال بارگذاری سبد خرید">
      <div className="flex flex-col gap-4"><Skeleton className="h-8 w-40" /><Skeleton className="h-44 rounded-3xl" /><Skeleton className="h-20 rounded-2xl" /><Skeleton className="h-20 rounded-2xl" /></div>
      <Skeleton className="h-72 rounded-3xl" />
    </div>
  );
}
