import type { Metadata } from 'next';
import Link from 'next/link';
import { LogIn, UtensilsCrossed } from 'lucide-react';
import { ThemeSwitch } from '@/components/ThemeSwitch';

export const metadata: Metadata = {
  title: { default: 'خوراک‌گردی', template: '%s | خوراک‌گردی کافه‌یار' },
  description: 'کافه، شیرینی‌فروشی و رستوران‌های نزدیک را پیدا کنید؛ منو، ساعت کاری و سفارش آنلاین.',
  robots: { index: true, follow: true },
};

/** Public marketplace frame: light header, content, calm footer. No session needed. */
export default function ExploreLayout({ children }: LayoutProps<'/explore'>) {
  return (
    <div className="flex min-h-screen flex-col bg-bg">
      <header className="sticky top-0 z-30 border-b border-border bg-bg/85 backdrop-blur-md">
        <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
          <Link href="/explore" className="flex items-center gap-2.5">
            <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><UtensilsCrossed className="size-5" aria-hidden="true" /></span>
            <span className="leading-tight">
              <span className="block font-bold">خوراک‌گردی</span>
              <span className="block text-[11px] text-text-muted">به‌همت کافه‌یار</span>
            </span>
          </Link>
          <div className="ms-auto flex items-center gap-2">
            <ThemeSwitch />
            <Link href="/login" className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-sm text-text-muted hover:text-text">
              <LogIn className="size-4" aria-hidden="true" /><span className="hidden sm:inline">ورود کسب‌وکارها</span>
            </Link>
          </div>
        </div>
      </header>
      <main id="main" className="flex-1">{children}</main>
      <footer className="mt-8 border-t border-border bg-surface">
        <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-[2fr_1fr_1fr] sm:px-6">
          <div className="flex flex-col gap-3">
            <span className="flex items-center gap-2.5">
              <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><UtensilsCrossed className="size-5" aria-hidden="true" /></span>
              <span className="font-black">خوراک‌گردی</span>
            </span>
            <p className="max-w-sm text-sm leading-7 text-text-muted">کافه‌ها، شیرینی‌فروشی‌ها و رستوران‌های شهرتان را پیدا کنید؛ منو، ساعت کاری، تخفیف‌ها و سفارش آنلاین بدون واسطه.</p>
          </div>
          <nav aria-label="خوراک‌گردی" className="flex flex-col gap-2 text-sm">
            <p className="font-bold">خوراک‌گردی</p>
            <Link href="/explore" className="text-text-muted hover:text-text">صفحه‌ی اول</Link>
            <Link href="/explore?open_now=1" className="text-text-muted hover:text-text">همین حالا باز</Link>
            <Link href="/explore?offers=1" className="text-text-muted hover:text-text">تخفیف‌دارها</Link>
          </nav>
          <nav aria-label="کسب‌وکارها" className="flex flex-col gap-2 text-sm">
            <p className="font-bold">کسب‌وکارها</p>
            <Link href="/login" className="text-text-muted hover:text-text">معرفی فروشگاه در خوراک‌گردی</Link>
            <Link href="/login" className="text-text-muted hover:text-text">تبلیغ در خوراک‌گردی</Link>
            <Link href="/login" className="text-text-muted hover:text-text">ورود به پنل کافه‌یار</Link>
          </nav>
        </div>
        <p className="border-t border-border py-4 text-center text-xs text-text-subtle">کافه‌یار • نرم‌افزار ابری کافه و رستوران</p>
      </footer>
    </div>
  );
}
