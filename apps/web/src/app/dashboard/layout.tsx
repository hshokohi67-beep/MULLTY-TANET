import Link from 'next/link';
import { ChefHat } from 'lucide-react';
import { formatPhone } from '@cafe/locale';
import { requireMembership } from '@/lib/auth';
import { getBillingStatus } from '@/lib/billing';
import { SCREEN_FEATURES } from '@/lib/billing-types';
import { AppShell, type NavGroup } from './AppShell';
import { TimeClockButton } from './TimeClockButton';

export default async function DashboardLayout({ children }: LayoutProps<'/dashboard'>) {
  const { user, membership, memberships, can } = await requireMembership();
  const billing = await getBillingStatus();

  const groups: NavGroup[] = [
    {
      title: 'عملیات روزانه',
      items: [
        { href: '/dashboard', label: 'پیشخوان', icon: 'overview' },
        ...(can('reports.view') ? [{ href: '/dashboard/reports', label: 'گزارش‌ها', icon: 'reports' }] : []),
        ...(can('orders.view') ? [{ href: '/dashboard/orders', label: 'سفارش‌ها', icon: 'orders' }] : []),
        ...(can('kds.manage') ? [{ href: '/dashboard/kitchen', label: 'آشپزخانه', icon: 'kitchen' }] : []),
        ...(can('orders.view') ? [{ href: '/dashboard/tables', label: 'میزها و QR', icon: 'tables' }] : []),
      ],
    },
    {
      title: 'منو و فروش',
      items: [
        ...(can('catalog.view') ? [{ href: '/dashboard/menu', label: 'منو', icon: 'menu' }] : []),
        ...(can('discounts.manage') ? [{ href: '/dashboard/discounts', label: 'تخفیف‌ها', icon: 'discounts' }] : []),
        ...(can('storefront.manage') ? [{ href: '/dashboard/stories', label: 'استوری‌ها', icon: 'stories' }] : []),
        ...(can('delivery.manage') ? [{ href: '/dashboard/delivery', label: 'محدوده‌های ارسال', icon: 'delivery' }] : []),
        ...(can('marketplace.manage') ? [{ href: '/dashboard/marketplace', label: 'بازارگاه', icon: 'marketplace' }] : []),
      ],
    },
    {
      title: 'انبار و خرید',
      items: [
        ...(can('inventory.view') ? [{ href: '/dashboard/inventory', label: 'انبار', icon: 'inventory' }] : []),
        ...(can('purchasing.manage') ? [{ href: '/dashboard/purchases', label: 'خرید', icon: 'purchases' }] : []),
      ],
    },
    {
      title: 'کارکنان و هزینه‌ها',
      items: [
        ...(can('staff.manage') ? [{ href: '/dashboard/staff', label: 'کارکنان و شیفت', icon: 'staff' }] : []),
        ...(can('expenses.manage') ? [{ href: '/dashboard/expenses', label: 'هزینه‌ها', icon: 'expenses' }] : []),
      ],
    },
    {
      title: 'مشتریان و مالی',
      items: [
        ...(can('customers.view') ? [{ href: '/dashboard/customers', label: 'مشتریان', icon: 'customers' }] : []),
        ...(can('loyalty.manage') ? [{ href: '/dashboard/club', label: 'باشگاه مشتریان', icon: 'club' }] : []),
        ...(can('payments.view') ? [{ href: '/dashboard/payments', label: 'پرداخت‌ها', icon: 'payments' }] : []),
      ],
    },
    {
      title: 'تنظیمات',
      items: [
        ...(can('branches.view') ? [{ href: '/dashboard/branches', label: 'شعبه‌ها و ساعات کاری', icon: 'branches' }] : []),
        ...(can('team.view') ? [{ href: '/dashboard/team', label: 'تیم و دسترسی‌ها', icon: 'team' }] : []),
        ...(can('settings.view') || can('tenant.view') ? [{ href: '/dashboard/settings', label: 'تنظیمات', icon: 'settings' }] : []),
        ...(can('billing.manage') ? [{ href: '/dashboard/billing', label: 'اشتراک و پرداخت', icon: 'billing' }] : []),
      ],
    },
  ].filter((g) => g.items.length > 0);

  // Screens outside the plan stay visible with a lock and lead to the upgrade view.
  for (const group of groups) {
    for (const item of group.items) {
      const feature = SCREEN_FEATURES[item.href];
      if (feature && billing?.features[feature] === false) item.locked = true;
    }
  }

  return (
    <AppShell
      groups={groups}
      tenantName={membership.tenant.name}
      userName={user.name}
      userPhone={user.phone ? formatPhone(user.phone) : null}
      canSwitchTenant={memberships.length > 1}
      permissions={membership.permissions}
      storefrontUrl={`/s/${membership.tenant.slug}`}
      billing={billing ? { state: billing.state, daysLeft: billing.days_left, status: billing.status, canManage: can('billing.manage') } : null}
      isPlatformAdmin={user.is_platform_admin}
      topActions={<>
        {can('attendance.self') ? <TimeClockButton /> : null}
        {can('kds.operate') ? (
          <Link href="/kds" target="_blank" className="hidden h-9 items-center gap-2 rounded-lg border border-border bg-surface px-3 text-sm text-text-muted transition-colors hover:text-text sm:inline-flex">
            <ChefHat className="size-4" aria-hidden="true" /> نمایشگر آشپزخانه
          </Link>
        ) : null}
      </>}
    >
      {children}
    </AppShell>
  );
}
