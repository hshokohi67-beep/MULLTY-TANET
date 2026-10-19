import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Cake, Coins, Crown, Gift, ShoppingBag, TrendingUp, UserPlus, Wallet } from 'lucide-react';
import { brandTokens } from '@cafe/ui';
import { formatMoney, formatNumber, formatPercent } from '@cafe/locale';
import { getClub, getStorefront, storeUrl } from '@/lib/storefront';

export async function generateMetadata({ params }: PageProps<'/s/[tenant]/club'>): Promise<Metadata> {
  const { tenant } = await params;
  const store = await getStorefront(tenant);

  return store ? { title: `باشگاه مشتریان ${store.name}`, alternates: { canonical: storeUrl(tenant, '/club') } } : {};
}

const multiplier = (bp: number) => `${formatNumber(bp / 10000, bp % 10000 ? 1 : 0)} برابر`;

/**
 * «باشگاه مشتریان»: how the café's club works, told as a path through its tiers (each in its own
 * colour), with the rewards on the way. Built only from the program settings; no customer data.
 */
export default async function ClubPage({ params }: PageProps<'/s/[tenant]/club'>) {
  const { tenant } = await params;
  const [store, club] = await Promise.all([getStorefront(tenant), getClub(tenant)]);
  if (!store || !club) notFound();

  const pointWorth = club.point_value * club.points_per_100k;
  const steps = [
    { icon: ShoppingBag, title: 'خرید کنید', text: `هر ۱۰۰ هزار تومان خرید = ${formatNumber(club.points_per_100k)} امتیاز.` },
    { icon: Coins, title: 'امتیاز جمع کنید', text: `هر امتیاز ${formatMoney(club.point_value)} می‌ارزد؛ از ${formatNumber(club.min_redeem_points)} امتیاز به بعد به اعتبار کیف پول تبدیل می‌شود.` },
    ...(club.tiers.length ? [{ icon: TrendingUp, title: 'سطح بالاتر بروید', text: 'با خرید بیشتر به سطح بعدی می‌رسید و امتیاز هر خریدتان چند برابر می‌شود.' }] : []),
    ...(club.wallet_payments ? [{ icon: Wallet, title: 'با کیف پول بپردازید', text: 'اعتبار کیف پول را هنگام سفارش خرج کنید.' }] : []),
  ];
  const gifts = [
    club.birthday_points || club.birthday_wallet_gift ? { icon: Cake, title: 'هدیه‌ی تولد', text: [club.birthday_points ? `${formatNumber(club.birthday_points)} امتیاز` : null, club.birthday_wallet_gift ? `${formatMoney(club.birthday_wallet_gift)} اعتبار` : null].filter(Boolean).join(' و ') } : null,
    club.referral_referrer_reward || club.referral_referee_reward ? { icon: UserPlus, title: 'دوستانتان را دعوت کنید', text: `شما ${formatMoney(club.referral_referrer_reward)} و دوستتان ${formatMoney(club.referral_referee_reward)} اعتبار می‌گیرید.` } : null,
  ].filter((g) => g !== null);

  return (
    <div className="mx-auto max-w-3xl pt-6">
      <section className="relative overflow-hidden rounded-[2rem] bg-brand px-6 py-12 text-center text-on-brand sm:py-16">
        <p aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-2 select-none text-center text-7xl font-black opacity-10 sm:text-8xl">CLUB</p>
        <Crown className="mx-auto size-10" aria-hidden="true" />
        <h1 className="mt-3 text-3xl font-black sm:text-4xl">باشگاه مشتریان {store.name}</h1>
        <p className="mx-auto mt-3 max-w-md leading-7 opacity-90">
          هر خرید شما را یک قدم جلوتر می‌برد. {pointWorth ? `هر ۱۰۰ هزار تومان خرید، ${formatMoney(pointWorth)} اعتبار برمی‌گرداند.` : null}
        </p>
        <Link href={`/s/${tenant}/account`} className="mt-6 inline-flex h-12 items-center gap-2 rounded-2xl bg-surface px-6 font-bold text-text shadow-[var(--shadow-md)] hover:bg-surface-muted">
          عضو شوید یا وارد شوید
        </Link>
      </section>

      <section aria-labelledby="how" className="mt-10">
        <h2 id="how" className="mb-4 text-xl font-black">چطور کار می‌کند؟</h2>
        <ol className="grid gap-3 sm:grid-cols-2">
          {steps.map((s, i) => (
            <li key={s.title} className="flex gap-3 rounded-3xl border border-border bg-surface p-4">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand"><s.icon className="size-5" aria-hidden="true" /></span>
              <div>
                <p className="text-xs text-text-subtle">گام {formatNumber(i + 1)}</p>
                <p className="font-bold">{s.title}</p>
                <p className="mt-0.5 text-sm leading-6 text-text-muted">{s.text}</p>
              </div>
            </li>
          ))}
        </ol>
      </section>

      {club.tiers.length ? (
        <section aria-labelledby="tiers" className="mt-10">
          <h2 id="tiers" className="mb-1 text-xl font-black">مسیر سطح‌ها</h2>
          <p className="mb-5 text-sm text-text-muted">از اولین خرید عضو می‌شوید؛ سطح شما با جمع خریدهایتان بالا می‌رود.</p>
          <ol className="relative flex flex-col gap-4 ps-8 before:absolute before:inset-y-4 before:start-[0.95rem] before:w-0.5 before:bg-border">
            {club.tiers.map((t, i) => {
              const c = brandTokens(t.color);

              return (
                <li key={t.name} className="relative">
                  <span aria-hidden="true" className="absolute -start-8 top-5 size-[1.9rem] rounded-full border-4 border-bg" style={{ background: c?.brand ?? 'var(--color-brand)' }} />
                  <div className="overflow-hidden rounded-3xl border border-border bg-surface">
                    <div className="flex items-center gap-4 p-5" style={c ? { background: `linear-gradient(to left, ${c.brandSoft}, transparent 70%)` } : undefined}>
                      <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl text-xl font-black shadow-[var(--shadow-sm)]"
                        style={c ? { background: c.brand, color: c.onBrand } : undefined}>{formatNumber(i + 1)}</span>
                      <div className="min-w-0 flex-1">
                        <p className="text-xl font-black">{t.name}</p>
                        <p className="text-sm text-text-muted">{t.min_spend ? `از ${formatMoney(t.min_spend)} خرید` : 'از اولین خرید'}</p>
                      </div>
                      <div className="text-end">
                        <p className="text-2xl font-black tabular">{multiplier(t.points_multiplier)}</p>
                        <p className="text-xs text-text-muted">امتیاز هر خرید</p>
                      </div>
                    </div>
                    {t.perks ? <p className="border-t border-border px-5 py-3 text-sm leading-7 text-text-muted">{t.perks}</p> : null}
                  </div>
                </li>
              );
            })}
          </ol>
        </section>
      ) : null}

      {club.cashback.length ? (
        <section aria-labelledby="cashback" className="mt-10">
          <h2 id="cashback" className="mb-4 text-xl font-black">کش‌بک</h2>
          <ul className="grid gap-3 sm:grid-cols-2">
            {club.cashback.map((r) => (
              <li key={r.name} className="rounded-3xl border border-border bg-surface p-4">
                <p className="text-2xl font-black text-brand tabular">{r.kind === 'percent' ? formatPercent(r.value / 10000, 0) : formatMoney(r.value)}</p>
                <p className="mt-1 font-semibold">{r.name}</p>
                <p className="mt-0.5 text-sm text-text-muted">
                  {[r.category ? `روی «${r.category}»` : 'روی کل سفارش', r.min_spend ? `از ${formatMoney(r.min_spend)} خرید` : null, r.max_reward ? `تا ${formatMoney(r.max_reward)}` : null].filter(Boolean).join(' • ')}
                </p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {gifts.length ? (
        <section aria-labelledby="gifts" className="mt-10">
          <h2 id="gifts" className="mb-4 text-xl font-black">هدیه‌ها</h2>
          <ul className="grid gap-3 sm:grid-cols-2">
            {gifts.map((g) => (
              <li key={g.title} className="flex gap-3 rounded-3xl border border-border bg-surface p-4">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent"><g.icon className="size-5" aria-hidden="true" /></span>
                <div><p className="font-bold">{g.title}</p><p className="mt-0.5 text-sm text-text-muted">{g.text}</p></div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <div className="mt-10 flex justify-center">
        <Link href={`/s/${tenant}/account`} className="inline-flex h-12 items-center gap-2 rounded-2xl bg-brand px-6 font-bold text-on-brand hover:bg-brand-strong">
          <Gift className="size-5" aria-hidden="true" />همین حالا عضو شوید
        </Link>
      </div>
    </div>
  );
}
