import type { Metadata } from 'next';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branding, SettingItem, SettingsMeta, Tenant } from '@/lib/types';
import { BrandingForm, GeneralSettingsForm, PaymentSettingsForm, PreorderSettingsForm, ReportSettingsForm, TenantProfileForm } from './SettingsForms';

export const metadata: Metadata = { title: 'تنظیمات' };

export default async function SettingsPage() {
  const { can } = await requireMembership();

  const [tenant, branding, settings] = await Promise.all([
    api<{ data: Tenant }>('/tenant').then((r) => r.data),
    api<{ data: Branding }>('/tenant/branding').then((r) => r.data),
    can('settings.view') ? api<{ data: SettingItem[]; meta: SettingsMeta }>('/tenant/settings') : Promise.resolve(null),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="تنظیمات" description="اطلاعات کسب‌وکار، برند، سئو و تنظیمات عمومی" />
      <TenantProfileForm tenant={tenant} readOnly={!can('tenant.update')} />
      <BrandingForm branding={branding} readOnly={!can('branding.update')} />
      {settings ? <GeneralSettingsForm settings={settings.data} readOnly={!can('settings.update')} /> : null}
      {settings ? <PaymentSettingsForm settings={settings.data} meta={settings.meta} readOnly={!can('settings.update')} /> : null}
      {settings ? <PreorderSettingsForm settings={settings.data} readOnly={!can('settings.update')} /> : null}
      {settings ? <ReportSettingsForm settings={settings.data} readOnly={!can('settings.update')} /> : null}
    </div>
  );
}
