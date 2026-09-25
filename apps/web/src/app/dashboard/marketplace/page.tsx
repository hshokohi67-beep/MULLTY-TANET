import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { CheckCircle2, Circle, ExternalLink, XCircle } from 'lucide-react';
import { Card, CardHeader, cx } from '@cafe/ui';
import { BrandStyles, StoreCard } from '@/components/explore/ExploreParts';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { StoreCard as Card_ } from '@/lib/marketplace-types';
import { ListingEditor, type ListingData } from './ListingEditor';

export const metadata: Metadata = { title: 'بازارگاه' };

interface Payload {
  listing: ListingData & { hidden_reason: string | null };
  eligible: boolean;
  checks: { key: string; label: string; ok: boolean; required: boolean }[];
  preview: Card_ | null;
  public_path: string | null;
  catalog: { categories: { key: string; label: string }[]; amenities: { key: string; label: string }[]; price_levels: { key: number; label: string }[]; max_categories: number };
}

/** The café's entry in «کافه‌گردی»: what customers see, and what's still missing to appear. */
export default async function MarketplacePage() {
  const { can } = await requireMembership();
  if (!can('marketplace.manage')) redirect('/dashboard');
  const { data } = await api<{ data: Payload }>('/marketplace/listing');

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="بازارگاه" description="کافه‌تان را در «کافه‌گردی» معرفی کنید؛ مشتری‌های تازه با جست‌وجوی شهر، نوع کافه و امکانات پیدایتان می‌کنند."
        actions={data.public_path ? (
          <Link href={data.public_path} target="_blank" className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-3.5 text-sm font-medium hover:bg-surface-muted">
            <ExternalLink className="size-4" aria-hidden="true" />دیدن صفحه‌ی عمومی
          </Link>
        ) : null} />

      <div className="grid items-start gap-6 lg:grid-cols-[3fr_2fr]">
        <ListingEditor listing={data.listing} catalog={data.catalog} />

        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader title={data.eligible ? 'در کافه‌گردی نمایش داده می‌شود' : 'هنوز نمایش داده نمی‌شود'}
              description={data.eligible ? 'مشتری‌ها همین حالا می‌توانند کافه‌تان را پیدا کنند.' : 'موارد ضروری را کامل کنید.'} />
            <ul className="flex flex-col gap-2.5 p-5 text-sm">
              {data.checks.map((c) => (
                <li key={c.key} className="flex items-start gap-2.5">
                  {c.ok ? <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                    : c.required ? <XCircle className="mt-0.5 size-4 shrink-0 text-danger" aria-hidden="true" />
                      : <Circle className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" />}
                  <span className={cx(c.ok ? 'text-text' : c.required ? 'text-danger' : 'text-text-muted')}>{c.label}</span>
                  <span className="sr-only">{c.ok ? '(انجام شده)' : c.required ? '(ضروری)' : '(پیشنهادی)'}</span>
                </li>
              ))}
            </ul>
            <p className="border-t border-border px-5 py-3 text-xs text-text-muted">
              لوگو و کاور از <Link href="/dashboard/settings#brand" className="text-brand hover:underline">تنظیمات برند</Link>، شهر و نشانی از <Link href="/dashboard/branches" className="text-brand hover:underline">شعبه‌ها</Link>.
            </p>
          </Card>
          {data.preview ? (
            <section aria-label="پیش‌نمایش" className="flex flex-col gap-2">
              <p className="text-sm font-semibold text-text-muted">پیش‌نمایش کارت شما</p>
              <BrandStyles stores={[data.preview]} />
              <StoreCard s={data.preview} />
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}
