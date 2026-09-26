import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { Crown, Gift, MapPin, Pencil, Plus, Receipt, Sparkles, Wallet } from 'lucide-react';
import { Badge, Card, CardHeader, EmptyState, type Tone } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney, formatNumber } from '@cafe/locale';
import { DeleteAddress, LogoutButton, ProfileForm, RedeemPoints, ReferralBox, ReorderButton } from '@/components/store/AccountClient';
import { ApiError } from '@/lib/api';
import { getStorefront, readCookie, sf } from '@/lib/storefront';
import type { Club, Customer, CustomerAddress } from '@/lib/storefront-types';
import type { Order } from '@/lib/types';

export const metadata: Metadata = { title: 'حساب من', robots: { index: false, follow: false } };

const STATUS_TONE: Record<string, Tone> = {
  pending_payment: 'warning', placed: 'info', accepted: 'info', preparing: 'info', ready: 'success',
  out_for_delivery: 'info', completed: 'neutral', cancelled: 'danger', rejected: 'danger',
};

type TrackedOrder = Order & { tracking_token?: string };

/** The customer's club card, orders (track / order again), addresses and profile. */
export default async function AccountPage({ params }: PageProps<'/s/[tenant]/account'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();
  if (!(await readCookie(tenant, 'customer'))) redirect(`/s/${tenant}/login?next=account`);

  let customer: Customer;
  let club: Club | null;
  let orders: TrackedOrder[];
  let addresses: CustomerAddress[];
  try {
    [customer, club, orders, addresses] = await Promise.all([
      sf<{ data: Customer }>(tenant, '/customer/profile').then((r) => r.data),
      sf<{ data: Club }>(tenant, '/customer/club').then((r) => r.data).catch(() => null),
      sf<{ data: TrackedOrder[] }>(tenant, '/customer/orders').then((r) => r.data),
      sf<{ data: CustomerAddress[] }>(tenant, '/customer/addresses').then((r) => r.data),
    ]);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) redirect(`/s/${tenant}/login?next=account`);
    throw error;
  }

  const clubOn = club?.program.enabled ?? false;
  const progress = club?.next_tier ? Math.min(1, club.lifetime_spend / Math.max(1, club.next_tier.min_spend)) : 1;

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 pt-6">
      <header className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">سلام{customer.name ? `، ${customer.name.split(/\s+/)[0]}` : ''}</h1>
          <p className="text-sm text-text-muted" dir="ltr">{customer.phone}</p>
        </div>
        <LogoutButton />
      </header>

      {club && clubOn ? (
        <section aria-label="باشگاه مشتریان" className="relative overflow-hidden rounded-3xl bg-brand p-5 text-on-brand shadow-[var(--shadow-lg)]">
          <div aria-hidden="true" className="absolute -end-10 -top-10 size-40 rounded-full bg-on-brand/10" />
          <div aria-hidden="true" className="absolute -bottom-16 end-16 size-40 rounded-full bg-on-brand/5" />
          <div className="relative flex flex-col gap-5">
            <div className="flex items-center justify-between">
              <p className="flex items-center gap-2 font-semibold"><Crown className="size-5" aria-hidden="true" />{club.tier?.name ?? 'عضو باشگاه'}</p>
              <p className="text-sm opacity-80">باشگاه {store.name}</p>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="flex items-center gap-1.5 text-sm opacity-80"><Wallet className="size-4" aria-hidden="true" />کیف پول</p>
                <p className="tabular mt-1 text-2xl font-black">{formatMoney(club.wallet_balance)}</p>
              </div>
              <div>
                <p className="flex items-center gap-1.5 text-sm opacity-80"><Sparkles className="size-4" aria-hidden="true" />امتیاز</p>
                <p className="tabular mt-1 text-2xl font-black">{formatNumber(club.points)}</p>
                <p className="text-xs opacity-80">معادل {formatMoney(club.points_value)}</p>
              </div>
            </div>
            {club.next_tier ? (
              <div>
                <div className="h-2 overflow-hidden rounded-full bg-on-brand/20" role="progressbar" aria-valuenow={Math.round(progress * 100)} aria-valuemin={0} aria-valuemax={100} aria-label={`پیشرفت تا سطح ${club.next_tier.name}`}>
                  <div className="h-full rounded-full bg-on-brand" style={{ width: `${progress * 100}%` }} />
                </div>
                <p className="mt-1.5 text-xs opacity-90">{formatMoney(club.next_tier.remaining)} خرید دیگر تا سطح «{club.next_tier.name}»</p>
              </div>
            ) : null}
          </div>
        </section>
      ) : null}

      {club && clubOn ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card className="p-5">
            <CardHeader icon={<Sparkles />} title="امتیازهای شما" description={`هر ۱۰ هزار تومان خرید = ${formatNumber(club.program.points_per_100k)} امتیاز`} />
            <div className="mt-4"><RedeemPoints points={club.points} min={club.program.min_redeem_points} pointValue={club.program.point_value} /></div>
          </Card>
          <Card className="p-5">
            <CardHeader icon={<Gift />} title="دعوت از دوستان"
              description={club.program.referral_referrer_reward ? `برای هر دوستی که با کد شما اولین خرید را کند ${formatMoney(club.program.referral_referrer_reward)} هدیه می‌گیرید.` : 'کد خودتان را برای دوستانتان بفرستید.'} />
            <div className="mt-4"><ReferralBox code={club.referral_code} canApply={orders.length === 0} /></div>
          </Card>
        </div>
      ) : null}

      <Card className="p-5">
        <CardHeader icon={<Receipt />} title="سفارش‌های من" />
        {orders.length === 0 ? (
          <EmptyState title="هنوز سفارشی ندارید" description="اولین سفارش‌تان اینجا می‌آید." action={<Link href={`/s/${tenant}/menu`} className="text-sm font-semibold text-brand hover:underline">دیدن منو</Link>} />
        ) : (
          <ul className="mt-3 divide-y divide-border">
            {orders.map((o) => (
              <li key={o.id} className="flex flex-wrap items-center gap-3 py-3">
                <div className="min-w-0 flex-1">
                  <p className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold">#{formatNumber(o.daily_number)}</span>
                    <Badge tone={STATUS_TONE[o.status] ?? 'neutral'} dot>{o.status_label}</Badge>
                    <span className="tabular text-sm font-semibold">{formatMoney(o.total)}</span>
                  </p>
                  <p className="truncate text-sm text-text-muted">{o.items?.map((i) => `${formatNumber(i.quantity)}× ${i.product_name}`).join('، ')}</p>
                  <p className="text-xs text-text-subtle">{formatJalaliDateTime(o.placed_at, store.timezone)} • {o.branch?.name} • {o.type_label}</p>
                </div>
                <div className="flex gap-2">
                  {o.tracking_token && !['completed', 'cancelled', 'rejected'].includes(o.status) ? (
                    <a href={`/s/${tenant}/track/${o.id}#t=${encodeURIComponent(o.tracking_token)}`} className="inline-flex h-9 items-center rounded-lg bg-brand px-3 text-sm font-medium text-on-brand hover:bg-brand-strong">پیگیری</a>
                  ) : null}
                  {o.branch && o.type !== 'qr_table' ? <ReorderButton orderId={o.id} branchId={o.branch.id} /> : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card className="p-5">
        <CardHeader icon={<MapPin />} title="آدرس‌های من" actions={
          <Link href={`/s/${tenant}/account/addresses/new`} className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline"><Plus className="size-4" aria-hidden="true" />آدرس جدید</Link>
        } />
        {addresses.length === 0 ? (
          <p className="mt-3 text-sm text-text-muted">برای سفارش با پیک، یک آدرس اضافه کنید.</p>
        ) : (
          <ul className="mt-3 flex flex-col gap-2">
            {addresses.map((a) => (
              <li key={a.id} className="flex items-center gap-3 rounded-xl bg-surface-muted px-3 py-2.5">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold">{a.title}{a.is_default ? <span className="ms-2 text-xs font-normal text-brand">پیش‌فرض</span> : null}</p>
                  <p className="truncate text-sm text-text-muted">{a.city}، {a.address}</p>
                </div>
                <Link href={`/s/${tenant}/account/addresses/${a.id}`} aria-label={`ویرایش ${a.title}`} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface hover:text-text"><Pencil className="size-4" /></Link>
                <DeleteAddress id={a.id} />
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card className="p-5">
        <CardHeader title="مشخصات" />
        <div className="mt-3"><ProfileForm customer={customer} /></div>
      </Card>
    </div>
  );
}
