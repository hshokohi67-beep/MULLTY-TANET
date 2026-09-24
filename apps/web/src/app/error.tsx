'use client';

import { Button } from '@cafe/ui';

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <main id="main" className="flex min-h-screen flex-col items-center justify-center gap-3 px-4 text-center">
      <h1 className="text-lg font-semibold">مشکلی پیش آمد</h1>
      <p className="max-w-sm text-sm text-text-muted">بارگذاری این بخش ممکن نشد. لطفاً دوباره تلاش کنید؛ اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.</p>
      <Button onClick={reset}>تلاش دوباره</Button>
    </main>
  );
}
