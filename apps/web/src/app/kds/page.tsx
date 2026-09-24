import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { ApiError } from '@/lib/api';
import { kdsApi, kdsCredentials } from '@/lib/kds';
import type { KdsMe } from '@/lib/types';
import { KdsScreen } from './KdsScreen';

export const metadata: Metadata = { title: 'نمایشگر آشپزخانه' };

export default async function KdsPage() {
  const creds = await kdsCredentials();

  if (!creds) {
    redirect('/kds/pair');
  }

  let me: KdsMe;
  try {
    me = (await kdsApi<{ data: KdsMe }>('/kds/me')).data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      return (
        <main id="main" className="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
          <h1 className="text-xl font-bold">{creds.device ? 'اتصال این دستگاه لغو شده است' : 'به نمایشگر آشپزخانه دسترسی ندارید'}</h1>
          <p className="text-text-muted">
            {creds.device ? 'از پنل مدیریت یک کد اتصال تازه بگیرید.' : 'مدیر کافه باید دسترسی «کار با نمایشگر آشپزخانه» را به نقش شما بدهد.'}
          </p>
          <Link href={creds.device ? '/kds/pair' : '/dashboard'} className="text-brand hover:underline">{creds.device ? 'اتصال دوباره' : 'بازگشت به پنل'}</Link>
        </main>
      );
    }
    throw error;
  }

  if (me.branches.length === 0) {
    return (
      <main id="main" className="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
        <h1 className="text-xl font-bold">هنوز ایستگاه آشپزخانه‌ای تعریف نشده</h1>
        <p className="text-text-muted">در پنل مدیریت، بخش «آشپزخانه»، دست‌کم یک ایستگاه (مثلاً «بار قهوه») بسازید.</p>
      </main>
    );
  }

  return <KdsScreen me={me} isDevice={creds.device} />;
}
