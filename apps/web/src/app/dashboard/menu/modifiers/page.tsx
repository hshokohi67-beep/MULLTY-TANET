import type { Metadata } from 'next';
import { Card, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { ModifierGroup } from '@/lib/types';
import { ModifierGroupEditor } from './ModifierGroupEditor';

export const metadata: Metadata = { title: 'افزودنی‌ها و انتخاب‌ها' };

export default async function ModifiersPage() {
  const { can } = await requireMembership();
  const { data: groups } = await api<{ data: ModifierGroup[] }>('/catalog/modifier-groups');
  const canManage = can('catalog.manage');

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="افزودنی‌ها و انتخاب‌ها"
        description="گروه‌ها را یک‌بار بسازید و به هر تعداد آیتم وصل کنید؛ مثل «نوع شیر» (یکی اجباری) یا «افزودنی‌ها» (اختیاری، چندتایی)."
      />
      {canManage ? <ModifierGroupEditor /> : null}
      {groups.length === 0 ? (
        <Card><EmptyState title="هنوز گروهی نساخته‌اید" description="با فرم بالا اولین گروه را بسازید." /></Card>
      ) : (
        groups.map((group) => <ModifierGroupEditor key={group.id} group={group} readOnly={!canManage} />)
      )}
    </div>
  );
}
