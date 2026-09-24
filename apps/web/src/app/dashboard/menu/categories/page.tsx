import type { Metadata } from 'next';
import { Card, CardHeader, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Category } from '@/lib/types';
import { CategoryForm, CategoryRow } from './CategoryForms';

export const metadata: Metadata = { title: 'دسته‌بندی‌های منو' };

/** Flattens the tree into display order with depth, so nesting reads naturally in a list. */
function flatten(categories: Category[]): { category: Category; depth: number }[] {
  const byParent = new Map<string | null, Category[]>();
  for (const c of categories) byParent.set(c.parent_id, [...(byParent.get(c.parent_id) ?? []), c]);

  const out: { category: Category; depth: number }[] = [];
  const walk = (parent: string | null, depth: number) => {
    for (const c of byParent.get(parent) ?? []) {
      out.push({ category: c, depth });
      walk(c.id, depth + 1);
    }
  };
  walk(null, 0);

  return out;
}

export default async function CategoriesPage() {
  const { can } = await requireMembership();
  const { data: categories } = await api<{ data: Category[] }>('/catalog/categories');
  const canManage = can('catalog.manage');
  const tree = flatten(categories);
  // Only the first two levels can have children (max depth 3).
  const parents = tree.filter((t) => t.depth < 2).map((t) => ({ id: t.category.id, name: `${'— '.repeat(t.depth)}${t.category.name}` }));

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="دسته‌بندی‌ها" description="حداکثر سه سطح؛ مثلاً «نوشیدنی‌ها ← گرم ← قهوه»." />
      {canManage ? <CategoryForm parents={parents} /> : null}
      <Card>
        <CardHeader title="همه‌ی دسته‌بندی‌ها" />
        {tree.length === 0 ? (
          <EmptyState title="هنوز دسته‌بندی ندارید" description="دسته‌بندی‌ها منو را برای مشتری مرتب و قابل‌جستجو می‌کنند." />
        ) : (
          <ul className="divide-y divide-border">
            {tree.map(({ category, depth }) => (
              <CategoryRow key={category.id} category={category} depth={depth} parents={parents.filter((p) => p.id !== category.id)} readOnly={!canManage} />
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
