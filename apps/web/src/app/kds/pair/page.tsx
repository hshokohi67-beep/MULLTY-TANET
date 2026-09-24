import type { Metadata } from 'next';
import { PairForm } from './PairForm';

export const metadata: Metadata = { title: 'اتصال به آشپزخانه' };

export default async function PairPage({ searchParams }: PageProps<'/kds/pair'>) {
  const params = await searchParams;
  const tenant = typeof params.t === 'string' ? params.t : '';

  return (
    <main id="main" className="mx-auto flex min-h-dvh max-w-sm flex-col items-center justify-center gap-6 px-4 text-center">
      <div aria-hidden="true" className="flex size-16 items-center justify-center rounded-2xl bg-brand-soft text-brand">
        <svg viewBox="0 0 24 24" className="size-8" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          <rect x="3" y="4" width="18" height="13" rx="2" /><path d="M8 21h8M12 17v4" />
        </svg>
      </div>
      <div>
        <h1 className="text-2xl font-bold">اتصال این دستگاه به آشپزخانه</h1>
        <p className="mt-2 text-sm text-text-muted">
          کد ۶ رقمی را از «پنل مدیریت ← آشپزخانه ← دستگاه‌ها» وارد کنید. این کد فقط ۱۰ دقیقه اعتبار دارد.
        </p>
      </div>
      <PairForm tenant={tenant} />
    </main>
  );
}
