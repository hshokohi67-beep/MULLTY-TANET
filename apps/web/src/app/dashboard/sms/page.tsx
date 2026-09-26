import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { SmsCentre as Data } from '@/lib/sms-types';
import type { Tenant } from '@/lib/types';
import { SmsCentre } from './SmsCentre';

export const metadata: Metadata = { title: 'پیامک' };

/** The café's own SMS: its panel and line, automatic messages, campaigns and the send log. */
export default async function SmsPage() {
  const { can, user } = await requireMembership();
  if (!can('sms.manage')) redirect('/dashboard');
  const [{ data }, { data: tenant }] = await Promise.all([api<{ data: Data }>('/sms'), api<{ data: Tenant }>('/tenant')]);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="پیامک" description="پیامک‌های کافه با پنل و خط خود شما ارسال می‌شود؛ پیام‌های خودکار سفارش و تولد، کمپین برای مشتریانی که اجازه داده‌اند، و گزارش هر ارسال." />
      <SmsCentre data={data} ownerPhone={user.phone ?? ''} timezone={tenant.timezone} />
    </div>
  );
}
