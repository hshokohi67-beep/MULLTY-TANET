import Link from 'next/link';
import { ChefHat } from 'lucide-react';
import { formatPhone } from '@cafe/locale';
import { requireMembership } from '@/lib/auth';
import { AppShell, type NavGroup } from './AppShell';

export default async function DashboardLayout({ children }: LayoutProps<'/dashboard'>) {
  const { user, membership, memberships, can } = await requireMembership();

  const groups: NavGroup[] = [
    {
      title: 'عملیات روزانه',
      items: [
        { href: '/dashboard', label: 'پیشخوان', icon: 'overview' },
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
      ],
    },
  ].filter((g) => g.items.length > 0);

  return (
    <AppShell
      groups={groups}
      tenantName={membership.tenant.name}
      userName={user.name}
      userPhone={user.phone ? formatPhone(user.phone) : null}
      canSwitchTenant={memberships.length > 1}
      permissions={membership.permissions}
      storefrontUrl={`/s/${membership.tenant.slug}`}
      topActions={can('kds.operate') ? (
        <Link href="/kds" target="_blank" className="hidden h-9 items-center gap-2 rounded-lg border border-border bg-surface px-3 text-sm text-text-muted transition-colors hover:text-text sm:inline-flex">
          <ChefHat className="size-4" aria-hidden="true" /> نمایشگر آشپزخانه
        </Link>
      ) : null}
    >
      {children}
    </AppShell>
  );
}
