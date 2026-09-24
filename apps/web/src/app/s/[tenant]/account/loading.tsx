import { Skeleton } from '@cafe/ui';

export default function Loading() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 pt-6" aria-busy="true" aria-label="در حال بارگذاری حساب">
      <Skeleton className="h-10 w-48" /><Skeleton className="h-48 rounded-3xl" />
      <div className="grid gap-4 md:grid-cols-2"><Skeleton className="h-40 rounded-2xl" /><Skeleton className="h-40 rounded-2xl" /></div>
      <Skeleton className="h-64 rounded-2xl" />
    </div>
  );
}
