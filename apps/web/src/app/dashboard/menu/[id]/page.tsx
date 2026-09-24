import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Category, ModifierGroup, Product } from '@/lib/types';
import { BranchPricesForm, DeleteProductButton, ImagesManager, ModifierGroupsForm, ProductDetailsForm, VariantsForm } from './ProductEditor';

export const metadata: Metadata = { title: 'ویرایش آیتم' };

export default async function ProductPage({ params }: PageProps<'/dashboard/menu/[id]'>) {
  const { id } = await params;
  const { can } = await requireMembership();

  let product: Product;
  try {
    product = (await api<{ data: Product }>(`/catalog/products/${encodeURIComponent(id)}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const [{ data: categories }, { data: groups }, { data: branches }] = await Promise.all([
    api<{ data: Category[] }>('/catalog/categories'),
    api<{ data: ModifierGroup[] }>('/catalog/modifier-groups'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  const canManage = can('catalog.manage');
  const canPrice = can('prices.manage');

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={product.name}
        description={<Link href="/dashboard/menu" className="text-brand hover:underline">بازگشت به منو</Link>}
        actions={canManage ? <DeleteProductButton productId={product.id} name={product.name} /> : null}
      />
      <ProductDetailsForm product={product} categories={categories} readOnly={!canManage} />
      <VariantsForm product={product} readOnly={!(canManage && canPrice)} />
      {branches.length > 1 && canPrice ? <BranchPricesForm product={product} branches={branches} /> : null}
      <ModifierGroupsForm product={product} groups={groups} readOnly={!canManage} />
      <ImagesManager product={product} readOnly={!canManage} />
    </div>
  );
}
