import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { StaffLanding } from '@/lib/landing-types';
import { storeLink } from '@/lib/store-links';
import { getMenu, getStorefront } from '@/lib/storefront';
import type { Tenant } from '@/lib/types';
import { LandingEditor } from './LandingEditor';

export const metadata: Metadata = { title: 'صفحه‌ی معرفی' };

/** The café's landing page: its look, words, photos and section order, with a live preview. */
export default async function LandingPage() {
  const { can } = await requireMembership();
  if (!can('storefront.manage')) redirect('/dashboard');

  const [{ data: landing }, { data: tenant }] = await Promise.all([
    api<{ data: StaffLanding }>('/storefront/landing'),
    api<{ data: Tenant }>('/tenant'),
  ]);
  // The preview renders with the same public data the storefront uses (cached, no personal data).
  const store = await getStorefront(tenant.slug);
  const menu = store?.branches[0] ? await getMenu(tenant.slug, store.branches[0].slug) : null;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="صفحه‌ی معرفی"
        description="صفحه‌ی اول فروشگاه آنلاین شما: ظاهرش را انتخاب کنید، متن و عکس بگذارید و بخش‌ها را بچینید. دکمه‌ی «منو و سفارش» مشتری را به منو می‌برد."
      />
      {store ? (
        <LandingEditor initial={landing} tenant={tenant.slug} store={store} menu={menu} storeUrl={storeLink(tenant.slug)} />
      ) : (
        <p className="text-text-muted">فروشگاه آنلاین این کافه هنوز فعال نیست.</p>
      )}
    </div>
  );
}
