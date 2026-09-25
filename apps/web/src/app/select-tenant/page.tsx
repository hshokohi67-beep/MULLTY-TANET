import Link from 'next/link';
import type { Metadata } from 'next';
import { Button, Card, CardHeader, EmptyState } from '@cafe/ui';
import { chooseTenant, logout } from '@/app/actions/auth';
import { requireStaff } from '@/lib/auth';

export const metadata: Metadata = { title: 'انتخاب کسب‌وکار' };

export default async function SelectTenantPage() {
  const me = await requireStaff();

  return (
    <main id="main" className="flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader title={`${me.user.name}، خوش آمدید`} description="کدام کسب‌وکار را می‌خواهید مدیریت کنید؟" />
        {me.memberships.length === 0 ? (
          <EmptyState
            title="هنوز به هیچ کسب‌وکاری دسترسی ندارید"
            description="از مالک کافه بخواهید شما را با همین شماره موبایل به تیم اضافه کند."
            action={<form action={logout}><Button type="submit" variant="secondary">خروج</Button></form>}
          />
        ) : (
          <ul className="divide-y divide-border">
            {me.memberships.map((m) => (
              <li key={m.tenant.id}>
                <form action={chooseTenant} className="flex items-center justify-between gap-3 px-5 py-3">
                  <input type="hidden" name="slug" value={m.tenant.slug} />
                  <span className="font-medium">{m.tenant.name}</span>
                  <Button type="submit" size="sm">ورود</Button>
                </form>
              </li>
            ))}
          </ul>
        )}
        {me.user.is_platform_admin ? (
          <div className="border-t border-border px-5 py-3">
            <Link href="/platform" className="text-sm font-medium text-brand hover:underline">مدیریت پلتفرم (مشترکان و پلن‌ها)</Link>
          </div>
        ) : null}
      </Card>
    </main>
  );
}
