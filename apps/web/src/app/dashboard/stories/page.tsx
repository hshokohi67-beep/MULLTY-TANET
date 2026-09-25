import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Category, Product, Tenant } from '@/lib/types';
import { StoriesManager, type StaffStory } from './StoriesManager';

export const metadata: Metadata = { title: 'استوری‌ها' };

/** Stories shown at the top of the online menu: photo, short text and one call to action. */
export default async function StoriesPage({ searchParams }: PageProps<'/dashboard/stories'>) {
  const { can } = await requireMembership();
  if (!can('storefront.manage')) redirect('/dashboard');

  const [{ data: stories }, { data: products }, { data: categories }, branches, { data: tenant }] = await Promise.all([
    api<{ data: StaffStory[] }>('/stories'),
    api<{ data: Product[] }>('/catalog/products?per_page=100'),
    api<{ data: Category[] }>('/catalog/categories'),
    can('branches.view') ? api<{ data: Branch[] }>('/branches').then((r) => r.data) : Promise.resolve([] as Branch[]),
    api<{ data: Tenant }>('/tenant'),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="استوری‌ها" description="عکس محصول جدید، تخفیف امروز یا منوی فصلی را بالای منوی آنلاین نشان دهید. هر استوری به‌طور پیش‌فرض ۲۴ ساعت دیده می‌شود." />
      <StoriesManager
        stories={stories}
        products={products.filter((p) => p.is_active).map((p) => ({ id: p.id, name: p.name }))}
        categories={categories.filter((c) => c.is_active).map((c) => ({ id: c.id, name: c.name }))}
        branches={branches.map((b) => ({ id: b.id, name: b.name }))}
        storeUrl={`/s/${tenant.slug}`}
        startNew={(await searchParams).new === '1'}
      />
    </div>
  );
}
