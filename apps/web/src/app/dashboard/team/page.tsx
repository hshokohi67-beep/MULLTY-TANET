import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { Badge, Card, CardHeader } from '@cafe/ui';
import { formatPhone } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Role, TeamMember } from '@/lib/types';
import { AddMemberForm } from './AddMemberForm';

export const metadata: Metadata = { title: 'تیم و دسترسی‌ها' };

export default async function TeamPage() {
  const { can } = await requireMembership();

  if (!can('team.view')) {
    redirect('/dashboard');
  }

  const [{ data: members }, { data: roles }] = await Promise.all([
    api<{ data: TeamMember[] }>('/team'),
    api<{ data: Role[] }>('/roles'),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="تیم و دسترسی‌ها" description="هر همکار فقط به بخش‌هایی دسترسی دارد که نقشش اجازه می‌دهد." />

      <Card>
        <CardHeader title="اعضای تیم" />
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <caption className="sr-only">فهرست اعضای تیم</caption>
            <thead className="bg-surface-muted text-text-muted">
              <tr>
                <th scope="col" className="px-5 py-2.5 text-start font-medium">نام</th>
                <th scope="col" className="px-5 py-2.5 text-start font-medium">موبایل</th>
                <th scope="col" className="px-5 py-2.5 text-start font-medium">نقش</th>
                <th scope="col" className="px-5 py-2.5 text-start font-medium">وضعیت</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {members.map((member) => (
                <tr key={member.id}>
                  <td className="px-5 py-3 font-medium">{member.user.name}</td>
                  <td className="px-5 py-3 whitespace-nowrap">{member.user.phone ? formatPhone(member.user.phone) : '—'}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-wrap gap-1">
                      {member.roles.map((role) => <Badge key={role.id} tone={role.key === 'owner' ? 'brand' : 'neutral'}>{role.name}</Badge>)}
                    </div>
                  </td>
                  <td className="px-5 py-3">
                    <Badge tone={member.status === 'active' ? 'success' : 'neutral'}>{member.status === 'active' ? 'فعال' : 'غیرفعال'}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      {can('team.manage') ? <AddMemberForm roles={roles.filter((r) => r.key !== 'owner')} /> : null}
    </div>
  );
}
