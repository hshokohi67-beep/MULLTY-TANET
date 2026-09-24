import { Skeleton } from '@cafe/ui';

/** Menu skeleton: hero, story ring, search, chips and cards in their real places (no layout jump). */
export default function Loading() {
  return (
    <div className="pt-4" aria-busy="true" aria-label="در حال بارگذاری منو">
      <Skeleton className="h-48 rounded-[1.75rem] sm:h-60" />
      <div className="mt-4 flex gap-3">
        {Array.from({ length: 5 }, (_, i) => <Skeleton key={i} className="size-[4.25rem] shrink-0 rounded-full" />)}
      </div>
      <Skeleton className="mt-4 h-12 rounded-2xl" />
      <div className="mt-3 flex gap-2">
        {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-9 w-24 shrink-0 rounded-full" />)}
      </div>
      <div className="mt-6 grid gap-3 md:grid-cols-2">
        {Array.from({ length: 6 }, (_, i) => (
          <div key={i} className="flex gap-3.5 rounded-3xl border border-border bg-surface p-3">
            <div className="flex flex-1 flex-col gap-2 py-1"><Skeleton className="h-5 w-2/3" /><Skeleton className="h-4 w-full" /><Skeleton className="mt-auto h-6 w-24 rounded-full" /></div>
            <Skeleton className="size-28 rounded-2xl" />
          </div>
        ))}
      </div>
    </div>
  );
}
