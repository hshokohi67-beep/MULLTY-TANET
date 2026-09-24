import { redirect } from 'next/navigation';
import { requireMembership } from '@/lib/auth';
import { MenuTabs } from './MenuTabs';

export default async function MenuLayout({ children }: LayoutProps<'/dashboard/menu'>) {
  const { can } = await requireMembership();

  if (!can('catalog.view')) {
    redirect('/dashboard');
  }

  const tabs = [
    { href: '/dashboard/menu', label: 'آیتم‌های منو' },
    { href: '/dashboard/menu/categories', label: 'دسته‌بندی‌ها' },
    { href: '/dashboard/menu/modifiers', label: 'افزودنی‌ها و انتخاب‌ها' },
    ...(can('prices.manage') ? [{ href: '/dashboard/menu/prices', label: 'تغییر گروهی قیمت' }] : []),
  ];

  return (
    <>
      <MenuTabs tabs={tabs} />
      {children}
    </>
  );
}
