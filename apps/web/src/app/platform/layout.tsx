import Link from 'next/link';
import { redirect } from 'next/navigation';
import { ArrowRight, ShieldCheck } from 'lucide-react';
import { ThemeSwitch } from '@/components/ThemeSwitch';
import { requireStaff } from '@/lib/auth';

/** Platform administration (not tied to a café). Only platform admins get past this layout. */
export default async function PlatformLayout({ children }: LayoutProps<'/platform'>) {
  const me = await requireStaff();
  if (!me.user.is_platform_admin) redirect('/dashboard');

  const link = 'rounded-lg px-3 py-1.5 text-sm text-text-muted hover:bg-surface-muted hover:text-text';

  return (
    <div className="min-h-screen bg-bg">
      <header className="sticky top-0 z-30 border-b border-border bg-bg/85 backdrop-blur-md">
        <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
          <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><ShieldCheck className="size-5" aria-hidden="true" /></span>
          <span className="font-bold">مدیریت پلتفرم</span>
          <nav className="ms-4 flex gap-1" aria-label="بخش‌ها">
            <Link href="/platform" className={link}>مشترکان</Link>
            <Link href="/platform/plans" className={link}>پلن‌ها</Link>
            <Link href="/platform/marketplace" className={link}>بازارگاه</Link>
          </nav>
          <div className="ms-auto flex items-center gap-2">
            <ThemeSwitch />
            {me.memberships.length ? <Link href="/dashboard" className={`${link} inline-flex items-center gap-1`}><ArrowRight className="size-4" aria-hidden="true" />پنل کافه</Link> : null}
          </div>
        </div>
      </header>
      <main id="main" className="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8">{children}</main>
    </div>
  );
}
