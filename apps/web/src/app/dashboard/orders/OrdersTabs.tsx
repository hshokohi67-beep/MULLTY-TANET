import Link from 'next/link';

/** «سفارش‌های باز | تاریخچه» switch shared by both order pages. */
export function OrdersTabs({ current }: { current: 'open' | 'history' }) {
  const tab = (key: 'open' | 'history', href: string, label: string) => (
    <Link
      href={href}
      aria-current={current === key ? 'page' : undefined}
      className={`rounded-full px-4 py-1.5 text-sm transition-colors ${current === key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text'}`}
    >
      {label}
    </Link>
  );

  return (
    <nav aria-label="نمای سفارش‌ها" className="flex w-fit gap-1 rounded-full bg-surface-muted p-1">
      {tab('open', '/dashboard/orders', 'سفارش‌های باز')}
      {tab('history', '/dashboard/orders/history', 'تاریخچه')}
    </nav>
  );
}
