import Link from 'next/link';

export default function NotFound() {
  return (
    <main id="main" className="flex min-h-screen flex-col items-center justify-center gap-3 px-4 text-center">
      <p className="text-5xl font-bold text-brand">۴۰۴</p>
      <h1 className="text-lg font-semibold">صفحه‌ای که دنبالش هستید پیدا نشد</h1>
      <p className="text-sm text-text-muted">ممکن است نشانی اشتباه باشد یا این صفحه حذف شده باشد.</p>
      <Link href="/" className="text-sm font-medium text-brand hover:underline">بازگشت به صفحه‌ی اصلی</Link>
    </main>
  );
}
