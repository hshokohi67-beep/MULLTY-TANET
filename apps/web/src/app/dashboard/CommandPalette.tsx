'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useId, useMemo, useRef, useState, useTransition, type ReactNode } from 'react';
import {
  Aperture, ArrowUpLeft, CircleOff, CircleCheck, Clock, ExternalLink, FileText, LayoutGrid, Moon, Package, Plus, ReceiptText, Search, Settings2, Tags, UserRound,
} from 'lucide-react';
import { cx } from '@cafe/ui';
import { toLatinDigits } from '@cafe/locale';
import { searchDashboard, toggleSoldOut, type SearchGroup } from '@/app/actions/palette';
import type { NavGroup } from './AppShell';

interface Entry {
  id: string;
  group: string;
  title: string;
  subtitle?: string;
  icon: ReactNode;
  keywords?: string;
  href?: string;
  external?: boolean;
  run?: () => Promise<string | void> | string | void;
}

/** Settings sections by anchor, with the words people actually type. */
const SETTINGS: { anchor: string; title: string; keywords: string; permission: string }[] = [
  { anchor: 'profile', title: 'اطلاعات کسب‌وکار', keywords: 'نام کافه واحد پول تومان ریال', permission: 'tenant.view' },
  { anchor: 'brand', title: 'برند، لوگو، کاور و سئو', keywords: 'رنگ لوگو کاور عکس گوگل سئو پوسته', permission: 'tenant.view' },
  { anchor: 'general', title: 'تماس و پیامک', keywords: 'تلفن اینستاگرام کاوه نگار پیامک sms', permission: 'settings.view' },
  { anchor: 'payments', title: 'پرداخت آنلاین (زرین‌پال)', keywords: 'درگاه زرین پال مرچنت پرداخت آنلاین', permission: 'settings.view' },
  { anchor: 'preorder', title: 'پیش‌سفارش و زمان‌بندی', keywords: 'پیش سفارش بازه ظرفیت ساعت تحویل تعطیلی', permission: 'settings.view' },
  { anchor: 'report', title: 'گزارش پایان روز', keywords: 'پیامک گزارش روزانه فروش', permission: 'settings.view' },
];

/** Extra words for pages, so «محصول» finds «منو» and «QR» finds «میزها». */
const PAGE_KEYWORDS: Record<string, string> = {
  '/dashboard/menu': 'محصول محصولات آیتم کالا قیمت',
  '/dashboard/orders': 'سفارش صندوق فاکتور',
  '/dashboard/tables': 'میز qr کیوآر',
  '/dashboard/kitchen': 'آشپزخانه kds ایستگاه تبلت',
  '/dashboard/customers': 'مشتری کاربر',
  '/dashboard/club': 'باشگاه امتیاز کش بک سطح',
  '/dashboard/discounts': 'تخفیف کوپن کد',
  '/dashboard/stories': 'استوری story',
  '/dashboard/payments': 'پرداخت تراکنش',
  '/dashboard/delivery': 'پیک ارسال محدوده',
  '/dashboard/team': 'کارمند همکار نقش دسترسی',
};

const normalize = (s: string) => toLatinDigits(s).replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, ' ').toLowerCase().trim();

function matches(entry: Entry, q: string): boolean {
  const hay = normalize(`${entry.title} ${entry.subtitle ?? ''} ${entry.keywords ?? ''}`);

  return q.split(/\s+/).every((word) => hay.includes(word));
}

const RECENT_KEY = 'palette-recent';

function readRecent(): Entry[] {
  try {
    return (JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]') as Entry[]).slice(0, 5);
  } catch {
    return [];
  }
}

/**
 * The command palette (Ctrl+K / ⌘K / «/»): pages, settings sections, live records and quick
 * actions in one keyboard-first list. Permissions decide what is offered; the API decides the rest.
 */
export function CommandPalette({ groups, permissions, storefrontUrl, open, onOpenChange }: {
  groups: NavGroup[];
  permissions: string[];
  storefrontUrl: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const router = useRouter();
  const dialog = useRef<HTMLDialogElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const listId = useId();
  const [query, setQuery] = useState('');
  const [records, setRecords] = useState<SearchGroup[]>([]);
  const [active, setActive] = useState(0);
  const [recent, setRecent] = useState<Entry[]>([]);
  const [status, setStatus] = useState<string | null>(null);
  const [searching, startSearch] = useTransition();
  const can = useCallback((p: string) => permissions.includes(p), [permissions]);

  // Global shortcuts.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const typing = e.target instanceof HTMLElement && (e.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName));
      if ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey)) { e.preventDefault(); onOpenChange(true); }
      else if (e.key === '/' && !typing) { e.preventDefault(); onOpenChange(true); }
    };
    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [onOpenChange]);

  useEffect(() => {
    const el = dialog.current;
    if (!el) return;
    if (open && !el.open) {
      el.showModal();
      setTimeout(() => { setRecent(readRecent()); input.current?.focus(); }, 0);
    }
    if (!open && el.open) el.close();
  }, [open]);

  // Live record search, debounced.
  useEffect(() => {
    const q = query.trim();
    if (q.length < 2) {
      const t = setTimeout(() => setRecords([]), 0);
      return () => clearTimeout(t);
    }
    const t = setTimeout(() => startSearch(async () => setRecords(await searchDashboard(q))), 220);

    return () => clearTimeout(t);
  }, [query]);

  const statics = useMemo<Entry[]>(() => {
    const pages: Entry[] = groups.flatMap((g) => g.items.map((i) => ({
      id: `page:${i.href}`, group: 'صفحه‌ها', title: i.label, subtitle: g.title, href: i.href, keywords: PAGE_KEYWORDS[i.href],
      icon: <LayoutGrid className="size-4" />,
    })));
    const settings: Entry[] = SETTINGS.filter((s) => can(s.permission)).map((s) => ({
      id: `setting:${s.anchor}`, group: 'تنظیمات', title: s.title, keywords: `تنظیمات ${s.keywords}`, href: `/dashboard/settings#${s.anchor}`,
      icon: <Settings2 className="size-4" />,
    }));
    const actionList: (Entry | null)[] = [
      can('catalog.manage') ? { id: 'act:product', group: 'کارها', title: 'افزودن آیتم به منو', keywords: 'جدید محصول', href: '/dashboard/menu#quick-add', icon: <Plus className="size-4" /> } : null,
      can('storefront.manage') ? { id: 'act:story', group: 'کارها', title: 'استوری جدید', keywords: 'story', href: '/dashboard/stories?new=1', icon: <Aperture className="size-4" /> } : null,
      can('discounts.manage') ? { id: 'act:discount', group: 'کارها', title: 'کد تخفیف جدید', keywords: 'کوپن', href: '/dashboard/discounts', icon: <Tags className="size-4" /> } : null,
      can('orders.view') ? { id: 'act:history', group: 'کارها', title: 'تاریخچه‌ی سفارش‌ها', keywords: 'گزارش فروش امروز دیروز', href: '/dashboard/orders/history', icon: <FileText className="size-4" /> } : null,
      { id: 'act:store', group: 'کارها', title: 'دیدن منوی آنلاین', keywords: 'فروشگاه سایت مشتری', href: storefrontUrl, external: true, icon: <ExternalLink className="size-4" /> },
      { id: 'act:theme', group: 'کارها', title: 'تغییر پوسته‌ی روشن / تیره', keywords: 'تم دارک شب', icon: <Moon className="size-4" />, run: () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch { /* storage unavailable */ }
        return next === 'dark' ? 'پوسته‌ی تیره' : 'پوسته‌ی روشن';
      } },
    ];
    const actions = actionList.filter((e): e is Entry => e !== null);

    return [...actions, ...pages, ...settings];
  }, [groups, can, storefrontUrl]);

  const entries = useMemo<Entry[]>(() => {
    const q = normalize(query);
    if (!q) return [...recent.map((r) => ({ ...r, group: 'اخیر', icon: <Clock className="size-4" /> })), ...statics.filter((e) => e.group === 'کارها')];

    const icon: Record<string, ReactNode> = { products: <Package className="size-4" />, categories: <LayoutGrid className="size-4" />, orders: <ReceiptText className="size-4" />, customers: <UserRound className="size-4" /> };
    const live: Entry[] = records.flatMap((g) => g.items.flatMap((item) => {
      const base: Entry = { id: `${g.group}:${item.id}`, group: g.label, title: item.title, subtitle: item.subtitle, href: item.href, icon: icon[g.group] };
      // Products get a one-keystroke «تمام شد / موجود شد».
      if (g.group === 'products' && item.active && can('availability.manage')) {
        return [base, {
          id: `soldout:${item.id}`, group: g.label, title: item.sold_out ? `«${item.title}» دوباره موجود شد` : `«${item.title}» تمام شد`,
          icon: item.sold_out ? <CircleCheck className="size-4 text-success" /> : <CircleOff className="size-4 text-warning" />,
          run: async () => {
            const result = await toggleSoldOut(item.id, !item.sold_out);
            if (!result.ok) return result.message ?? 'انجام نشد.';
            setRecords((rs) => rs.map((r) => ({ ...r, items: r.items.map((x) => (x.id === item.id ? { ...x, sold_out: !item.sold_out, subtitle: item.sold_out ? 'فعال' : 'تمام شده' } : x)) })));
            return item.sold_out ? `«${item.title}» دوباره موجود شد` : `«${item.title}» تمام شد`;
          },
        }];
      }
      return [base];
    }));

    return [...statics.filter((e) => matches(e, q)), ...live].slice(0, 40);
  }, [query, records, recent, statics, can]);

  // Keep the highlight in range as the list changes.
  const activeIndex = Math.min(active, Math.max(0, entries.length - 1));

  const choose = async (entry: Entry) => {
    if (entry.run) {
      const message = await entry.run();
      if (typeof message === 'string') setStatus(message);
      return;
    }
    if (!entry.href) return;
    if (!entry.id.startsWith('act:theme')) {
      try {
        const next = [{ id: entry.id, group: '', title: entry.title, subtitle: entry.subtitle, href: entry.href, external: entry.external }, ...readRecent().filter((r) => r.id !== entry.id)];
        localStorage.setItem(RECENT_KEY, JSON.stringify(next.slice(0, 5)));
      } catch { /* storage unavailable */ }
    }
    onOpenChange(false);
    if (entry.external) window.open(entry.href, '_blank', 'noopener');
    else router.push(entry.href);
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((i) => Math.min(entries.length - 1, i + 1)); }
    if (e.key === 'ArrowUp') { e.preventDefault(); setActive((i) => Math.max(0, i - 1)); }
    if (e.key === 'Enter' && entries[activeIndex]) { e.preventDefault(); void choose(entries[activeIndex]); }
  };

  let lastGroup = '';

  return (
    <dialog ref={dialog} onClose={() => { onOpenChange(false); setQuery(''); setStatus(null); setActive(0); }}
      onClick={(e) => { if (e.target === dialog.current) onOpenChange(false); }}
      aria-label="جست‌وجو و فرمان سریع"
      className="mx-auto mt-[10vh] w-[calc(100%-1.5rem)] max-w-xl overflow-clip bg-transparent p-0 text-text backdrop:bg-black/40 backdrop:backdrop-blur-[2px]">
      <div className="dialog-in flex max-h-[70dvh] flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-lg)]">
        <div className="flex items-center gap-3 border-b border-border px-4">
          <Search className={cx('size-5 shrink-0 text-text-subtle', searching && 'animate-pulse')} aria-hidden="true" />
          <input ref={input} value={query} onChange={(e) => { setQuery(e.target.value); setActive(0); setStatus(null); }} onKeyDown={onKeyDown}
            role="combobox" aria-expanded="true" aria-controls={listId} aria-activedescendant={entries[activeIndex] ? `${listId}-${activeIndex}` : undefined}
            aria-autocomplete="list" placeholder="جست‌وجو: صفحه، تنظیمات، محصول، شماره‌ی سفارش، مشتری…"
            className="h-14 min-w-0 flex-1 bg-transparent text-base placeholder:text-text-subtle focus:outline-none focus-visible:outline-none" />
          <kbd className="hidden rounded-md border border-border bg-surface-muted px-1.5 py-0.5 font-sans text-[11px] text-text-muted sm:inline">Esc</kbd>
        </div>

        {status ? <p role="status" className="border-b border-border bg-success-soft px-4 py-2 text-sm text-success">{status}</p> : null}

        <ul id={listId} role="listbox" aria-label="نتیجه‌ها" className="flex-1 overflow-y-auto p-2">
          {entries.length === 0 ? (
            <li className="px-3 py-10 text-center text-sm text-text-muted">{searching ? 'در حال جست‌وجو…' : query.trim().length < 2 ? 'چیزی بنویسید؛ مثلاً «لاته» یا «۲۳» یا «زرین‌پال»' : 'چیزی پیدا نشد.'}</li>
          ) : entries.map((entry, i) => {
            const header = entry.group !== lastGroup ? entry.group : null;
            lastGroup = entry.group;

            return (
              <li key={entry.id} role="presentation">
                {header ? <p className="px-3 pt-3 pb-1 text-[11px] font-semibold text-text-subtle" aria-hidden="true">{header}</p> : null}
                <div id={`${listId}-${i}`} role="option" aria-selected={i === activeIndex} onMouseMove={() => setActive(i)} onClick={() => void choose(entry)}
                  className={cx('flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5', i === activeIndex ? 'bg-brand-soft text-text' : 'text-text')}>
                  <span className={cx('flex size-8 shrink-0 items-center justify-center rounded-lg', i === activeIndex ? 'bg-surface text-brand' : 'bg-surface-muted text-text-muted')}>{entry.icon}</span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{entry.title}</span>
                    {entry.subtitle ? <span className="block truncate text-xs text-text-muted">{entry.subtitle}</span> : null}
                  </span>
                  {i === activeIndex ? <ArrowUpLeft className="size-4 text-brand" aria-hidden="true" /> : null}
                </div>
              </li>
            );
          })}
        </ul>

        <div className="hidden items-center gap-4 border-t border-border px-4 py-2 text-[11px] text-text-muted sm:flex">
          <span><kbd className="font-sans">↑↓</kbd> جابه‌جایی</span>
          <span><kbd className="font-sans">Enter</kbd> باز کردن</span>
          <span className="ms-auto"><kbd className="font-sans">Ctrl K</kbd> یا <kbd className="font-sans">/</kbd> از هر جای پنل</span>
        </div>
      </div>
    </dialog>
  );
}
