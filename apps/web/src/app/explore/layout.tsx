import type { Metadata } from 'next';
import Link from 'next/link';
import { Coffee, LogIn } from 'lucide-react';
import { ThemeSwitch } from '@/components/ThemeSwitch';

export const metadata: Metadata = {
  title: { default: 'کافه‌گردی', template: '%s | کافه‌گردی کافه‌یار' },
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
            <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><Coffee className="size-5" aria-hidden="true" /></span>
            <span className="leading-tight">
              <span className="block font-bold">کافه‌گردی</span>
              <span className="block text-[11px] text-text-muted">به‌همت کافه‌یار</span>
            </span>
          </Link>
          <div className="ms-auto flex items-center gap-2">
            <ThemeSwitch />
            <Link href="/login" className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-sm text-text-muted hover:text-text">
              <LogIn className="size-4" aria-hidden="true" /><span className="hidden sm:inline">ورود کافه‌داران</span>
            </Link>
          </div>
        </div>
      </header>
      <main id="main" className="flex-1">{children}</main>
      <footer className="border-t border-border py-6 text-center text-xs text-text-subtle">
        کافه‌ی خودتان را اینجا معرفی کنید: پنل کافه‌یار ← بازارگاه
      </footer>
    </div>
  );
}
