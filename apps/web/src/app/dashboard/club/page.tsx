import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { Card, CardHeader } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Category, LoyaltyProgram } from '@/lib/types';
import { CashbackRuleEditor, ProgramForm, TierEditor } from './ClubForms';

export const metadata: Metadata = { title: 'باشگاه مشتریان' };

export default async function ClubPage() {
  const { can } = await requireMembership();

  if (!can('loyalty.manage')) {
    redirect('/dashboard');
  }

  const [{ data: program }, { data: categories }] = await Promise.all([
    api<{ data: LoyaltyProgram }>('/loyalty/program'),
    api<{ data: Category[] }>('/catalog/categories'),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="باشگاه مشتریان" description="امتیاز، کش‌بک، سطح‌ها، هدیه‌ی تولد و معرفی دوستان" />
      <ProgramForm settings={program.settings} />

      <Card>
        <CardHeader title="سطح‌ها" description="مشتری با رسیدن مجموع خریدش به حد هر سطح، خودکار ارتقا می‌یابد و سطحش کم نمی‌شود." />
        <ul className="divide-y divide-border">
          {program.tiers.map((t) => <TierEditor key={t.id} tier={t} />)}
          <TierEditor />
        </ul>
      </Card>

      <Card>
        <CardHeader title="قوانین کش‌بک" description="همه‌ی قوانینِ برقرار با هم جمع می‌شوند. بخشی از سفارش که با کیف پول پرداخت شده کش‌بک نمی‌گیرد." />
        <ul className="divide-y divide-border">
          {program.cashback_rules.map((r) => <CashbackRuleEditor key={r.id} rule={r} categories={categories} />)}
          <CashbackRuleEditor categories={categories} />
        </ul>
      </Card>
    </div>
  );
}
