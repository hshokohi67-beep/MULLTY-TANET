import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { requireMembership } from '@/lib/auth';
import { ImportWizard } from './ImportWizard';

export const metadata: Metadata = { title: 'ورود منو از اکسل' };

/** «ورود از اکسل»: products, categories and prices from a spreadsheet (e.g. a WooCommerce export). */
export default async function MenuImportPage() {
  const { can } = await requireMembership();
  if (!(can('catalog.manage') && can('prices.manage'))) redirect('/dashboard/menu');

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="ورود منو از اکسل" description="محصولات، دسته‌ها و قیمت‌ها را یکجا از فایل اکسل یا خروجی ووکامرس وارد کنید. برای تغییر قیمت‌ها هم می‌توانید همان فایل را دوباره بارگذاری کنید."
        actions={<Link href="/dashboard/menu" className="text-sm text-text-muted hover:text-text">→ بازگشت به منو</Link>} />
      <ImportWizard />
    </div>
  );
}
