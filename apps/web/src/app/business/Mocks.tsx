import type { ReactNode } from 'react';
import { BellRing, CakeSlice, ChefHat, Clock, Coffee, Crown, CupSoda, Flame, MessageSquareText, Plus, ShoppingBag, Snowflake, TrendingUp, Wallet } from 'lucide-react';
import { cx } from '@cafe/ui';

/*
 * Illustrations of the product for «کافه‌یار برای کسب‌وکارها», drawn with the design tokens (not
 * screenshots), so they stay sharp, light and right in both themes. The data in them is sample data.
 */

function Frame({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cx('overflow-hidden rounded-3xl border border-border bg-surface shadow-[var(--shadow-lg)]', className)}>{children}</div>;
}

const ITEMS = [
  { icon: Coffee, name: 'لاته', price: '۸۵,۰۰۰', kcal: '۱۹۰', mood: 'hot' as const, popular: true },
  { icon: CupSoda, name: 'آیس امریکانو', price: '۹۰,۰۰۰', kcal: '۱۵', mood: 'cold' as const, popular: false },
  { icon: CakeSlice, name: 'چیزکیک', price: '۱۴۵,۰۰۰', kcal: '۴۱۰', mood: null, popular: false },
];

/** A phone showing a café's menu: hero, chips, cards with calories and «+», the cart bar. */
export function PhoneMenu({ className }: { className?: string }) {
  return (
    <div className={cx('relative w-64 shrink-0 rounded-[2.4rem] border-[9px] border-text bg-bg shadow-[var(--shadow-lg)]', className)}>
      <div className="h-[31rem] overflow-hidden rounded-[1.8rem]">
        <div className="relative h-28 bg-gradient-to-br from-brand to-brand-strong p-3 text-on-brand">
          <span className="flex size-9 items-center justify-center rounded-xl bg-on-brand/20 text-sm font-black">ن</span>
          <p className="mt-2 text-lg font-black">کافه نارنج</p>
          <p className="text-[10px] opacity-85">باز است • تا ۲۳:۰۰</p>
        </div>
        <div className="flex gap-1.5 p-2.5">
          {['قهوه‌ی گرم', 'سرد', 'کیک'].map((c, i) => (
            <span key={c} className={cx('rounded-full px-2.5 py-1 text-[10px]', i === 0 ? 'bg-brand text-on-brand' : 'bg-surface text-text-muted ring-1 ring-border')}>{c}</span>
          ))}
        </div>
        <ul className="flex flex-col gap-2 px-2.5">
          {ITEMS.map((it) => (
            <li key={it.name} data-mood={it.mood ?? undefined} className="relative flex gap-2 rounded-2xl border border-border bg-surface p-2">
              <div className="min-w-0 flex-1">
                <p className="text-xs font-bold">{it.name}</p>
                <div className="mt-1 flex flex-wrap gap-1">
                  {it.mood ? (
                    <span className="inline-flex items-center gap-0.5 rounded-full bg-[var(--mood-soft)] px-1 text-[8px] text-[var(--mood-ink)]">
                      {it.mood === 'hot' ? <Flame className="size-2" /> : <Snowflake className="size-2" />}{it.mood === 'hot' ? 'گرم' : 'سرد'}
                    </span>
                  ) : null}
                  {it.popular ? <span className="inline-flex items-center gap-0.5 rounded-full bg-brand px-1 text-[8px] text-on-brand"><TrendingUp className="size-2" />پرفروش</span> : null}
                  <span className="rounded-full bg-surface-muted px-1 text-[8px] text-text-muted">{it.kcal} کالری</span>
                </div>
                <p className="mt-1.5 text-[10px] font-bold">{it.price} تومان</p>
              </div>
              <span data-mood={it.mood ?? undefined} className="photo-fallback flex size-14 shrink-0 items-center justify-center rounded-xl"><it.icon className="size-6" strokeWidth={1.5} /></span>
              <span className="absolute bottom-1 end-1 flex size-6 items-center justify-center rounded-full bg-brand text-on-brand ring-2 ring-surface"><Plus className="size-3.5" strokeWidth={3} /></span>
            </li>
          ))}
        </ul>
        <div className="glass absolute inset-x-3 bottom-3 flex items-center gap-2 rounded-2xl p-1.5 ps-3">
          <ShoppingBag className="size-4 text-brand" />
          <span className="flex-1 text-[10px] font-semibold">سبد خرید • ۲ مورد</span>
          <span className="rounded-xl bg-brand px-2.5 py-1.5 text-[10px] font-bold text-on-brand">۱۷۵,۰۰۰</span>
        </div>
      </div>
    </div>
  );
}

/** The order board: new, preparing, ready. */
export function OrderBoard({ className }: { className?: string }) {
  const cols = [
    { title: 'تازه', tone: 'bg-info-soft text-info', cards: [['#۱۴', 'میز ۶', '۲ لاته، ۱ کیک'], ['#۱۵', 'بیرون‌بر', '۱ اسپرسو']] },
    { title: 'در حال آماده‌سازی', tone: 'bg-warning-soft text-warning', cards: [['#۱۲', 'پیک', '۳ آیس لاته']] },
    { title: 'آماده', tone: 'bg-success-soft text-success', cards: [['#۱۱', 'میز ۲', 'املت، چای']] },
  ];

  return (
    <Frame className={cx('p-3', className)}>
      <div className="grid grid-cols-3 gap-2">
        {cols.map((c) => (
          <div key={c.title} className="flex flex-col gap-2 rounded-2xl bg-surface-muted p-2">
            <span className={cx('w-fit rounded-full px-2 py-0.5 text-[10px] font-semibold', c.tone)}>{c.title}</span>
            {c.cards.map(([n, where, items]) => (
              <div key={n} className="rounded-xl bg-surface p-2 shadow-[var(--shadow-sm)]">
                <p className="flex items-center justify-between text-xs font-black">{n}<span className="text-[9px] font-normal text-text-muted">{where}</span></p>
                <p className="mt-1 text-[10px] leading-4 text-text-muted">{items}</p>
              </div>
            ))}
          </div>
        ))}
      </div>
    </Frame>
  );
}

/** A kitchen ticket with a timer, and a phone notification for a new order. */
export function KitchenAndPush({ className }: { className?: string }) {
  return (
    <div className={cx('relative flex flex-col gap-3', className)}>
      <Frame className="p-4">
        <p className="flex items-center gap-2 text-sm font-bold"><ChefHat className="size-4 text-brand" />ایستگاه بار<span className="ms-auto inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-[10px] text-warning"><Clock className="size-3" />۴ دقیقه</span></p>
        <ul className="mt-3 flex flex-col gap-2 text-sm">
          {['۲ × لاته • شیر بادام', '۱ × آیس امریکانو', '۱ × چای ماسالا'].map((l, i) => (
            <li key={l} className={cx('flex items-center gap-2 rounded-xl px-3 py-2', i === 0 ? 'bg-success-soft text-success line-through' : 'bg-surface-muted')}>{l}</li>
          ))}
        </ul>
      </Frame>
      <div className="glass flex items-center gap-3 rounded-2xl p-3 shadow-[var(--shadow-md)]">
        <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><BellRing className="size-4" /></span>
        <div className="min-w-0 text-xs"><p className="font-bold">سفارش تازه #۱۶</p><p className="text-text-muted">شعبه مرکزی • در صف سفارش‌ها</p></div>
        <span className="ms-auto text-[10px] text-text-subtle">همین حالا</span>
      </div>
    </div>
  );
}

/** A club card, the wallet and an SMS on the customer's phone. */
export function ClubAndSms({ className }: { className?: string }) {
  return (
    <div className={cx('relative flex flex-col gap-3', className)}>
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-accent to-brand-strong p-5 text-on-brand shadow-[var(--shadow-lg)]">
        <Crown className="absolute -end-4 -top-4 size-24 opacity-15" />
        <p className="text-xs opacity-80">باشگاه کافه نارنج</p>
        <p className="mt-1 text-xl font-black">سطح طلایی</p>
        <div className="mt-4 flex items-end justify-between">
          <div><p className="text-[10px] opacity-80">امتیاز</p><p className="text-lg font-bold">۱,۲۴۰</p></div>
          <div className="text-end"><p className="flex items-center gap-1 text-[10px] opacity-80"><Wallet className="size-3" />کیف پول</p><p className="text-lg font-bold">۳۵۰,۰۰۰ تومان</p></div>
        </div>
      </div>
      <Frame className="p-3">
        <p className="flex items-center gap-1.5 text-[11px] text-text-muted"><MessageSquareText className="size-3.5" />پیامک از خط خود کافه</p>
        <p className="mt-2 w-fit max-w-[85%] rounded-2xl rounded-ss-sm bg-surface-muted px-3 py-2 text-xs leading-6">سارا جان تولدت مبارک! ۵۰ هزار تومان هدیه در کیف پولت نشست ☕<br /><span className="text-text-subtle">لغو۱۱</span></p>
      </Frame>
    </div>
  );
}

/** Stock levels and the real profit of a product. */
export function StockAndProfit({ className }: { className?: string }) {
  const rows = [['دانه‌ی قهوه', 72, ''], ['شیر', 18, 'کم است'], ['شکلات', 45, '']] as const;

  return (
    <Frame className={cx('p-4', className)}>
      <p className="text-sm font-bold">انبار شعبه مرکزی</p>
      <ul className="mt-3 flex flex-col gap-3">
        {rows.map(([name, pct, warn]) => (
          <li key={name} className="text-xs">
            <p className="flex items-center justify-between">{name}{warn ? <span className="rounded-full bg-danger-soft px-2 py-0.5 text-[10px] text-danger">{warn}</span> : <span className="text-text-muted">{pct}٪</span>}</p>
            <span className="mt-1.5 block h-2 overflow-hidden rounded-full bg-surface-muted"><span className={cx('block h-full rounded-full', warn ? 'bg-danger' : 'bg-brand')} style={{ width: `${pct}%` }} /></span>
          </li>
        ))}
      </ul>
      <div className="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-surface-muted p-3 text-center text-[10px]">
        <div><p className="text-text-muted">قیمت لاته</p><p className="mt-0.5 text-sm font-bold">۸۵ هزار</p></div>
        <div><p className="text-text-muted">بهای مواد</p><p className="mt-0.5 text-sm font-bold">۳۱ هزار</p></div>
        <div><p className="text-text-muted">سود</p><p className="mt-0.5 text-sm font-bold text-success">۶۴٪</p></div>
      </div>
    </Frame>
  );
}

/** Sales by hour and today's totals. */
export function ReportMock({ className }: { className?: string }) {
  const bars = [18, 26, 40, 34, 22, 30, 52, 70, 64, 48, 38, 56];

  return (
    <Frame className={cx('p-4', className)}>
      <div className="flex items-end justify-between">
        <div><p className="text-xs text-text-muted">فروش امروز</p><p className="text-2xl font-black">۱۲.۴ <span className="text-sm font-medium text-text-muted">میلیون تومان</span></p></div>
        <span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] text-success"><TrendingUp className="size-3" />۱۸٪ بیشتر از هفته‌ی قبل</span>
      </div>
      <div className="mt-4 flex h-28 items-end gap-1.5" aria-hidden="true">
        {bars.map((h, i) => <span key={i} className={cx('flex-1 rounded-t-md', i === 7 ? 'bg-brand' : 'bg-brand/35')} style={{ height: `${h}%` }} />)}
      </div>
      <p className="mt-2 flex justify-between text-[10px] text-text-subtle"><span>۸ صبح</span><span>شلوغ‌ترین: ۱۵ تا ۱۶</span><span>۲۰</span></p>
    </Frame>
  );
}

/** A store card in «خوراک‌گردی», with the ad label on top. */
export function MarketplaceMock({ className }: { className?: string }) {
  return (
    <Frame className={cx('w-72', className)}>
      <div className="relative h-32 bg-gradient-to-br from-brand-soft to-accent-soft">
        <Coffee className="absolute inset-0 m-auto size-12 text-brand opacity-40" strokeWidth={1.3} />
        <span className="absolute start-3 top-3 rounded-full bg-scrim/60 px-2 py-0.5 text-[10px] text-on-media">تبلیغ</span>
      </div>
      <div className="p-3">
        <p className="font-bold">کافه نارنج</p>
        <p className="mt-1 text-xs text-text-muted">شیراز • ولیعصر • ۸۰۰ متر</p>
        <div className="mt-2 flex gap-1.5 text-[10px]">
          <span className="rounded-full bg-success-soft px-2 py-0.5 text-success">باز است</span>
          <span className="rounded-full bg-accent-soft px-2 py-0.5 text-accent">۲۰٪ تخفیف صبحانه</span>
        </div>
      </div>
    </Frame>
  );
}
