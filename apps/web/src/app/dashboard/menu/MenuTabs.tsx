'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cx } from '@cafe/ui';

export function MenuTabs({ tabs }: { tabs: { href: string; label: string }[] }) {
  const pathname = usePathname();

  return (
    <nav aria-label="بخش‌های منو" className="mb-6 border-b border-border">
      <ul className="-mb-px flex gap-1 overflow-x-auto">
        {tabs.map((tab) => {
          const active = tab.href === '/dashboard/menu'
            ? pathname === tab.href || /^\/dashboard\/menu\/(?!categories|modifiers|prices)[^/]+$/.test(pathname)
            : pathname.startsWith(tab.href);

          return (
            <li key={tab.href} className="shrink-0">
              <Link
                href={tab.href}
                aria-current={active ? 'page' : undefined}
                className={cx(
                  'block border-b-2 px-3 py-2 text-sm transition-colors',
                  active ? 'border-brand font-semibold text-brand-strong' : 'border-transparent text-text-muted hover:text-text',
                )}
              >
                {tab.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
