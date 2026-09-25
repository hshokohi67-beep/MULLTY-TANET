'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import {
  Aperture, Armchair, BarChart3, Boxes, ChefHat, CalendarClock, Receipt, ChevronDown, Crown, LayoutDashboard, LogOut, Menu, PanelRightClose, PanelRightOpen, ReceiptText,
  Search, Settings, ShoppingCart, Store, Tags, Truck, UserCog, Users, UtensilsCrossed, Wallet, X, type LucideIcon,
} from 'lucide-react';
import { cx } from '@cafe/ui';
import { logout } from '@/app/actions/auth';
import { ThemeSwitch } from '@/components/ThemeSwitch';
import { CommandPalette } from './CommandPalette';
import { NotificationBell } from './NotificationBell';

const ICONS: Record<string, LucideIcon> = {
  overview: LayoutDashboard, reports: BarChart3, orders: ReceiptText, kitchen: ChefHat, tables: Armchair, menu: UtensilsCrossed,
  discounts: Tags, stories: Aperture, inventory: Boxes, purchases: ShoppingCart, staff: CalendarClock, expenses: Receipt, delivery: Truck, customers: Users, club: Crown, payments: Wallet, branches: Store, team: UserCog, settings: Settings,
};

export interface NavLink { href: string; label: string; icon: keyof typeof ICONS | string; external?: boolean }
export interface NavGroup { title: string; items: NavLink[] }

function initials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('');
}

function Nav({ groups, collapsed, onNavigate }: { groups: NavGroup[]; collapsed: boolean; onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <nav aria-label="منوی اصلی" className="flex flex-col gap-5">
      {groups.map((group) => (
        <div key={group.title} className="flex flex-col gap-0.5">
          {!collapsed ? <p className="px-3 pb-1 text-[11px] font-semibold tracking-wide text-text-subtle">{group.title}</p> : <span className="mx-3 mb-1 h-px bg-border" aria-hidden="true" />}
          {group.items.map((item) => {
            const Icon = ICONS[item.icon] ?? LayoutDashboard;
            const active = item.href === '/dashboard' ? pathname === item.href : pathname.startsWith(item.href);

            return (
              <Link
                key={item.href}
                href={item.href}
                target={item.external ? '_blank' : undefined}
                onClick={onNavigate}
                aria-current={active ? 'page' : undefined}
                title={collapsed ? item.label : undefined}
                className={cx(
                  'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors duration-[var(--duration-fast)]',
                  collapsed && 'justify-center px-0',
                  active ? 'bg-brand-soft font-semibold text-brand-strong' : 'text-text-muted hover:bg-surface-muted hover:text-text',
                )}
              >
                {active ? <span className="absolute inset-y-2 start-0 w-0.5 rounded-full bg-brand" aria-hidden="true" /> : null}
                <Icon className={cx('size-[18px] shrink-0', active ? 'text-brand' : 'text-text-subtle group-hover:text-text-muted')} aria-hidden="true" />
                {collapsed ? <span className="sr-only">{item.label}</span> : <span className="truncate">{item.label}</span>}
              </Link>
            );
          })}
        </div>
      ))}
    </nav>
  );
}

/**
 * The panel frame: a grouped, icon-led sidebar (collapsible to a rail on desktop, a drawer on
 * phones), a quiet top bar with theme and account, and the page. The collapsed state is
 * remembered per browser.
 */
export function AppShell({ groups, tenantName, userName, userPhone, canSwitchTenant, topActions, permissions, storefrontUrl, children }: {
  groups: NavGroup[];
  permissions: string[];
  storefrontUrl: string;
  tenantName: string;
  userName: string;
  userPhone: string | null;
  canSwitchTenant: boolean;
  topActions?: ReactNode;
  children: ReactNode;
}) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const pathname = usePathname();

  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        setCollapsed(localStorage.getItem('sidebar-collapsed') === '1');
      } catch {
        // storage unavailable
      }
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  const toggleCollapsed = () => {
    setCollapsed((c) => {
      try {
        localStorage.setItem('sidebar-collapsed', c ? '0' : '1');
      } catch {
        // storage unavailable
      }
      return !c;
    });
  };

  const brand = (
    <Link href="/dashboard" className="flex items-center gap-2.5 px-2" onClick={() => setMobileOpen(false)}>
      <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand text-on-brand shadow-[var(--shadow-sm)]" aria-hidden="true">
        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1" /><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" /><path d="M6 2v2M10 2v2M14 2v2" /></svg>
      </span>
      {!collapsed ? (
        <span className="min-w-0 leading-tight">
          <span className="block text-sm font-bold text-text">کافه‌یار</span>
          <span className="block truncate text-xs text-text-muted">{tenantName}</span>
        </span>
      ) : null}
    </Link>
  );

  return (
    <div className={cx('min-h-dvh lg:grid', collapsed ? 'lg:grid-cols-[4.5rem_1fr]' : 'lg:grid-cols-[16rem_1fr]')}>
      {/* Desktop sidebar */}
      <aside className="hidden border-e border-border bg-surface lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col">
        <div className="flex h-16 items-center border-b border-border px-3">{brand}</div>
        <div className="flex-1 overflow-y-auto px-3 py-4">
          <Nav groups={groups} collapsed={collapsed} />
        </div>
        <div className="border-t border-border p-3">
          <button type="button" onClick={toggleCollapsed} className={cx('flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-text-muted hover:bg-surface-muted hover:text-text', collapsed && 'justify-center px-0')}>
            {collapsed ? <PanelRightOpen className="size-[18px]" aria-hidden="true" /> : <PanelRightClose className="size-[18px]" aria-hidden="true" />}
            {collapsed ? <span className="sr-only">باز کردن منو</span> : 'جمع کردن منو'}
          </button>
        </div>
      </aside>

      {/* Mobile drawer */}
      {mobileOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="منو">
          <button type="button" className="overlay-in absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} aria-label="بستن منو" />
          <div className="drawer-in absolute inset-y-0 start-0 flex w-72 max-w-[85vw] flex-col bg-surface shadow-[var(--shadow-lg)]">
            <div className="flex h-16 items-center justify-between border-b border-border px-3">
              {brand}
              <button type="button" onClick={() => setMobileOpen(false)} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted" aria-label="بستن منو"><X className="size-5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto px-3 py-4"><Nav groups={groups} collapsed={false} onNavigate={() => setMobileOpen(false)} /></div>
          </div>
        </div>
      ) : null}

      <div className="flex min-w-0 flex-col">
        <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-bg/85 px-4 backdrop-blur-md sm:px-6">
          <button type="button" onClick={() => setMobileOpen(true)} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted lg:hidden" aria-label="باز کردن منو">
            <Menu className="size-5" />
          </button>
          <span className="text-sm font-semibold lg:hidden">{tenantName}</span>

          <button type="button" onClick={() => setPaletteOpen(true)} aria-label="جست‌وجو و فرمان سریع (Ctrl+K)"
            className="ms-auto flex h-9 items-center gap-2 rounded-xl border border-border bg-surface px-2.5 text-sm text-text-subtle shadow-[var(--shadow-sm)] transition-colors hover:border-border-strong hover:text-text-muted sm:ms-0 sm:w-72 sm:px-3">
            <Search className="size-4 shrink-0" aria-hidden="true" />
            <span className="hidden flex-1 text-start sm:inline">جست‌وجو یا فرمان…</span>
            <kbd className="hidden rounded-md border border-border bg-surface-muted px-1.5 font-sans text-[11px] text-text-muted sm:inline" dir="ltr">Ctrl K</kbd>
          </button>

          <div className="flex items-center gap-2 sm:ms-auto">
            {topActions}
            <NotificationBell />
            <ThemeSwitch />
            <details className="group relative">
              <summary className="flex cursor-pointer list-none items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-surface-muted [&::-webkit-details-marker]:hidden">
                <span className="flex size-8 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-strong" aria-hidden="true">{initials(userName)}</span>
                <span className="hidden text-sm font-medium sm:inline">{userName}</span>
                <ChevronDown className="size-4 text-text-subtle transition-transform group-open:rotate-180" aria-hidden="true" />
              </summary>
              <div className="dialog-in absolute end-0 top-full z-40 mt-2 w-60 rounded-xl border border-border bg-surface-raised p-1.5 shadow-[var(--shadow-lg)]">
                <div className="border-b border-border px-3 py-2">
                  <p className="text-sm font-semibold">{userName}</p>
                  {userPhone ? <p className="text-xs text-text-muted" dir="ltr" style={{ textAlign: 'end' }}>{userPhone}</p> : null}
                </div>
                {canSwitchTenant ? <Link href="/select-tenant" className="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted"><Store className="size-4 text-text-subtle" />تغییر کسب‌وکار</Link> : null}
                <form action={logout}>
                  <button type="submit" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-danger hover:bg-danger-soft"><LogOut className="size-4" />خروج</button>
                </form>
              </div>
            </details>
          </div>
        </header>
        <CommandPalette groups={groups} permissions={permissions} storefrontUrl={storefrontUrl} open={paletteOpen} onOpenChange={setPaletteOpen} />
        <main id="main" key={pathname} className="page-in mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
          {children}
        </main>
      </div>
    </div>
  );
}
