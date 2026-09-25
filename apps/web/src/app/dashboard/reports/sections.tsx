import Link from 'next/link';
import type { ReactNode } from 'react';
import { AlertTriangle, Boxes, Clock, Coins, Crown, Package, Percent, ReceiptText, ShoppingBag, Store, Trash2, TrendingUp, UserPlus, Users } from 'lucide-react';
import { Alert, Badge, Card, CardHeader, cx, EmptyState, StatTile } from '@cafe/ui';
import { formatMoney, formatNumber, formatPercent, toPersianDigits } from '@cafe/locale';
import { MoneyColumnChart, MoneyHeatmap, MoneyShareBar } from '@/components/MoneyCharts';
import {
  change, jalaliShort, WEEK_DAYS, type BranchRow, type CustomersReport, type HoursReport, type InventoryReport, type ProductsReport, type Summary,
} from '@/lib/report-types';

/* Shared by the reports page and the print view. Server components only (charts are client leaves). */

export function StaleNote({ stale }: { stale: boolean }) {
  return stale ? <Alert tone="info">بخشی از روزهای این بازه هنوز در حال به‌روزرسانی است؛ چند دقیقه‌ی دیگر اعداد کامل می‌شوند.</Alert> : null;
}

const against = (compare: string) => (compare === 'last_year' ? 'نسبت به پارسال' : 'نسبت به دوره‌ی قبل');

function sharesOf<T>(rows: T[], key: (r: T) => string, label: (r: T) => string, value: (r: T) => number, max = 4) {
  const sorted = [...rows].sort((a, b) => value(b) - value(a));
  const parts = sorted.slice(0, max - 1).map((r) => ({ key: key(r), label: label(r), value: value(r) }));
  const rest = sorted.slice(max - 1).reduce((s, r) => s + value(r), 0);
  if (rest > 0) parts.push({ key: 'rest', label: 'سایر', value: rest });

  return parts;
}

/* ----------------------------------------- summary ----------------------------------------- */

export function SummarySection({ data, compare, print = false }: { data: Summary; compare: string; print?: boolean }) {
  const t = data.totals;
  const p = data.previous;
  const vs = against(compare);
  const delta = (now: number, before: number | undefined, upIsGood = true) => (p ? { ratio: change(now, before), against: vs, upIsGood } : undefined);
  const trend = data.series.points.map((x) => x.value);
  const loss = t.profit < 0;

  const costRows = [
    { key: 'cogs', label: 'بهای مواد مصرفی', value: t.cogs, before: p?.cogs, href: '/dashboard/inventory' },
    { key: 'labour', label: 'دستمزد', value: t.labour, before: p?.labour, href: '/dashboard/staff?tab=payroll' },
    { key: 'expenses', label: 'هزینه‌های جاری', value: t.expenses, before: p?.expenses, href: '/dashboard/expenses' },
    { key: 'waste', label: 'ضایعات', value: t.waste, before: p?.waste, href: '/dashboard/inventory' },
  ];
  const scale = Math.max(t.net_sales, costRows.reduce((s, r) => s + r.value, 0), 1);

  return (
    <div className="flex flex-col gap-6">
      <StaleNote stale={data.stale} />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="فروش" value={formatMoney(t.sales)} icon={<Coins />} delta={delta(t.sales, p?.sales)} trend={print || trend.length < 2 ? undefined : trend} />
        <StatTile label="سفارش" value={formatNumber(t.orders)} icon={<ReceiptText />} delta={delta(t.orders, p?.orders)} hint={t.cancelled ? `${formatNumber(t.cancelled)} لغو یا ردشده` : undefined} />
        <StatTile label="میانگین هر سفارش" value={formatMoney(t.average)} icon={<ShoppingBag />} delta={delta(t.average, p?.average)} hint={`${formatNumber(t.items)} قلم فروخته شد`} />
        <StatTile label={loss ? 'زیان' : 'سود'} value={formatMoney(Math.abs(t.profit))} icon={<TrendingUp />} delta={delta(t.profit, p?.profit)}
          hint={t.margin !== null ? (t.margin < -1 ? 'هزینه‌ها بیش از دو برابر فروش' : `حاشیه‌ی سود ${formatPercent(t.margin)}`) : 'فروشی در این بازه نیست'} />
      </div>

      <Card>
        <CardHeader icon={<TrendingUp />} title={data.series.unit === 'month' ? 'فروش ماه‌به‌ماه' : 'فروش روزانه'}
          description={data.compare ? `ستون‌ها: این بازه • خط: ${compare === 'last_year' ? 'همین بازه در پارسال' : 'دوره‌ی قبل'} (${data.compare.from_jalali} تا ${data.compare.to_jalali})` : `میانگین روزانه ${formatMoney(t.daily_average)}`} />
        <div className="p-5">
          {t.sales === 0 && !p?.sales ? <EmptyState icon={<TrendingUp />} title="در این بازه فروشی ثبت نشده" description="بازه‌ی دیگری انتخاب کنید." /> : (
            <MoneyColumnChart caption="فروش در این بازه" valueLabel="این بازه" referenceLabel={data.compare ? (compare === 'last_year' ? 'پارسال' : 'دوره‌ی قبل') : undefined}
              height={print ? 180 : 220}
              data={data.series.points.map((x) => ({ label: data.series.unit === 'month' ? x.label : jalaliShort(x.key), value: x.value, reference: x.reference ?? undefined }))} />
          )}
        </div>
      </Card>

      <div className={cx('grid gap-6', !print && 'lg:grid-cols-[3fr_2fr]')}>
        <Card>
          <CardHeader icon={<Coins />} title="سود و زیان" description="فروش خالص منهای بهای مواد، دستمزد، هزینه‌ها و ضایعات" />
          <div className="flex flex-col gap-3 p-5 text-sm">
            <Row label="فروش" value={t.sales} />
            {t.refunds ? <Row label="− بازپرداخت به مشتری" value={t.refunds} muted /> : null}
            <div>
              <Row label="فروش خالص" value={t.net_sales} strong />
              <div className="mt-1.5 h-2 rounded-full bg-success/75" style={{ width: `${(t.net_sales / scale) * 100}%` }} aria-hidden="true" />
            </div>
            {costRows.map((r) => (
              <div key={r.key}>
                <p className="flex items-baseline justify-between gap-2">
                  {print ? <span className="text-text-muted">− {r.label}</span> : <Link href={r.href} className="text-text-muted hover:text-text hover:underline">− {r.label}</Link>}
                  <span className="tabular">{formatMoney(r.value)}{t.net_sales > 0 ? <span className="ms-2 text-xs text-text-subtle">{formatPercent(r.value / t.net_sales)}</span> : null}</span>
                </p>
                <div className="mt-1 h-2 rounded-full bg-surface-muted"><div className="h-full rounded-full bg-text-subtle/50" style={{ width: `${(r.value / scale) * 100}%` }} /></div>
              </div>
            ))}
            <div className="mt-1 flex items-baseline justify-between border-t border-border pt-3">
              <span className="font-semibold">{loss ? 'زیان' : 'سود'}</span>
              <span className={cx('tabular text-lg font-bold', loss ? 'text-danger' : 'text-success')}>{formatMoney(Math.abs(t.profit))}</span>
            </div>
            {t.prime_cost !== null ? (
              <p className="rounded-lg bg-surface-muted px-3 py-2 text-xs text-text-muted">
                هزینه‌ی اصلی (مواد + دستمزد): <strong className={cx('tabular', t.prime_cost > 0.65 ? 'text-warning' : 'text-text')}>{t.prime_cost > 1 ? 'بیش از ۱۰۰٪' : formatPercent(t.prime_cost)}</strong> فروش
                {t.prime_cost > 0.65 ? ' — بالاتر از ۶۵٪ معمول کافه‌ها.' : ' — در محدوده‌ی سالم (زیر ۶۵٪).'}
              </p>
            ) : null}
            {t.cogs_coverage !== null && t.cogs_coverage < 1 ? (
              <p className="text-[11px] text-text-subtle">فقط {formatPercent(t.cogs_coverage)} از اقلام فروخته‌شده دستور پخت دارند؛ بهای مواد کمتر از واقع است.</p>
            ) : null}
          </div>
        </Card>

        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader icon={<Store />} title="کانال فروش" />
            <div className="p-5"><MoneyShareBar caption="سهم کانال‌های فروش" parts={sharesOf(data.channels, (c) => c.key, (c) => c.label, (c) => c.sales)} empty="فروشی نیست." /></div>
          </Card>
          <Card>
            <CardHeader icon={<Coins />} title="روش پرداخت" description="پرداخت‌های موفق، منهای بازپرداخت" />
            <div className="p-5"><MoneyShareBar caption="سهم روش‌های پرداخت" parts={sharesOf(data.payments, (x) => x.key, (x) => x.label, (x) => x.amount)} empty="پرداختی ثبت نشده." /></div>
          </Card>
          {data.goal ? (
            <Card>
              <CardHeader icon={<Percent />} title="هدف فروش" description={`${formatMoney(data.goal.daily)} در روز × ${toPersianDigits(data.period.days)} روز`} />
              <div className="p-5">
                <p className="flex justify-between text-sm"><span className="text-text-muted">{formatMoney(t.sales)} از {formatMoney(data.goal.target)}</span><strong className="tabular">{formatPercent(data.goal.ratio)}</strong></p>
                <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-surface-muted"><div className={cx('h-full rounded-full', data.goal.ratio >= 1 ? 'bg-success' : 'bg-brand')} style={{ width: `${Math.min(100, data.goal.ratio * 100)}%` }} /></div>
              </div>
            </Card>
          ) : null}
        </div>
      </div>

      {p ? (
        <Card>
          <CardHeader title="مقایسه با دوره‌ی مبنا" description={data.compare ? `${data.compare.from_jalali} تا ${data.compare.to_jalali}` : undefined} />
          <div className="overflow-x-auto">
            <table className="w-full min-w-[32rem] text-sm">
              <thead className="bg-surface-muted/60 text-xs text-text-muted">
                <tr><th className="px-4 py-2.5 text-start font-medium">شاخص</th><th className="px-4 py-2.5 text-end font-medium">این بازه</th><th className="px-4 py-2.5 text-end font-medium">مبنا</th><th className="px-4 py-2.5 text-end font-medium">تغییر</th></tr>
              </thead>
              <tbody className="divide-y divide-border">
                {([
                  ['فروش', t.sales, p.sales, true, true], ['سفارش', t.orders, p.orders, false, true], ['میانگین سفارش', t.average, p.average, true, true],
                  ['تخفیف', t.discounts, p.discounts, true, false], ['بهای مواد', t.cogs, p.cogs, true, false], ['دستمزد', t.labour, p.labour, true, false],
                  ['هزینه‌ها', t.expenses, p.expenses, true, false], ['سود', t.profit, p.profit, true, true], ['مشتری خریدار', t.buyers, p.buyers, false, true],
                ] as const).map(([label, now, before, money, upIsGood]) => {
                  const r = change(now, before);
                  const good = r === null || Math.abs(r) < 0.005 ? null : (r > 0) === upIsGood;

                  return (
                    <tr key={label}>
                      <td className="px-4 py-2.5">{label}</td>
                      <td className="tabular px-4 py-2.5 text-end font-medium">{money ? formatMoney(now) : formatNumber(now)}</td>
                      <td className="tabular px-4 py-2.5 text-end text-text-muted">{money ? formatMoney(before) : formatNumber(before)}</td>
                      <td className={cx('tabular px-4 py-2.5 text-end text-xs font-semibold', good === true && 'text-success', good === false && 'text-danger', good === null && 'text-text-subtle')}>
                        {r === null ? '—' : new Intl.NumberFormat('fa-IR', { style: 'percent', maximumFractionDigits: 0, signDisplay: 'exceptZero' }).format(r)}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>
      ) : null}
    </div>
  );
}

function Row({ label, value, strong, muted }: { label: string; value: number; strong?: boolean; muted?: boolean }) {
  return (
    <p className={cx('flex items-baseline justify-between gap-2', muted && 'text-text-muted')}>
      <span className={strong ? 'font-semibold' : undefined}>{label}</span>
      <span className={cx('tabular', strong && 'font-semibold')}>{formatMoney(value)}</span>
    </p>
  );
}

/* ----------------------------------------- products ----------------------------------------- */

const CLASS_TONE = { A: 'success', B: 'info', C: 'neutral' } as const;

export function ProductsSection({ data, sortLinks, limit = 100 }: { data: ProductsReport; sortLinks?: ReactNode; limit?: number }) {
  if (data.products.length === 0) {
    return <Card><EmptyState icon={<Package />} title="در این بازه محصولی فروخته نشده" description="بازه یا شعبه را تغییر دهید." /></Card>;
  }
  const max = Math.max(...data.products.map((p) => p.revenue), 1);
  const counts = { A: 0, B: 0, C: 0 };
  for (const p of data.products) counts[p.class]++;

  return (
    <div className="flex flex-col gap-6">
      <StaleNote stale={data.stale} />
      <div className="grid gap-4 sm:grid-cols-3">
        {(['A', 'B', 'C'] as const).map((c) => (
          <div key={c} className="flex items-center gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
            <span className={cx('flex size-10 items-center justify-center rounded-xl text-lg font-bold', c === 'A' ? 'bg-success-soft text-success' : c === 'B' ? 'bg-info-soft text-info' : 'bg-surface-muted text-text-muted')}>{c}</span>
            <div>
              <p className="font-semibold">{formatNumber(counts[c])} محصول</p>
              <p className="text-xs text-text-muted">{c === 'A' ? '۸۰٪ اول فروش؛ هرگز تمام نشوند' : c === 'B' ? '۱۵٪ بعدی؛ فرصت رشد' : '۵٪ آخر؛ کاندید حذف یا بازطراحی'}</p>
            </div>
          </div>
        ))}
      </div>
      <Card>
        <CardHeader icon={<Package />} title="فروش محصولات" description={`${formatNumber(data.products.length)} محصول • مجموع ${formatMoney(data.total_revenue)}`} actions={sortLinks} />
        <div className="overflow-x-auto">
          <table className="w-full min-w-[44rem] text-sm">
            <thead className="bg-surface-muted/60 text-xs text-text-muted">
              <tr>
                <th className="w-10 px-4 py-2.5 text-start font-medium">#</th>
                <th className="px-4 py-2.5 text-start font-medium">محصول</th>
                <th className="px-4 py-2.5 text-end font-medium">تعداد</th>
                <th className="w-64 px-4 py-2.5 text-start font-medium">فروش</th>
                <th className="px-4 py-2.5 text-end font-medium">بهای مواد</th>
                <th className="px-4 py-2.5 text-end font-medium">سود ناخالص</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {data.products.slice(0, limit).map((p, i) => (
                <tr key={p.key}>
                  <td className="tabular px-4 py-2.5 text-text-subtle">{toPersianDigits(i + 1)}</td>
                  <td className="px-4 py-2.5">
                    <p className="flex items-center gap-2 font-medium">{p.name}<Badge tone={CLASS_TONE[p.class]}>{p.class}</Badge></p>
                    <p className="text-xs text-text-muted">{p.category}</p>
                  </td>
                  <td className="tabular px-4 py-2.5 text-end">{formatNumber(p.quantity)}</td>
                  <td className="px-4 py-2.5">
                    <p className="flex justify-between gap-2"><span className="tabular font-medium">{formatMoney(p.revenue)}</span><span className="tabular text-xs text-text-subtle">{formatPercent(p.share)}</span></p>
                    <div className="mt-1 h-1.5 rounded-full bg-surface-muted"><div className="h-full rounded-full bg-brand" style={{ width: `${(p.revenue / max) * 100}%` }} /></div>
                  </td>
                  <td className="tabular px-4 py-2.5 text-end text-text-muted">{p.cost === null ? <span title="دستور پخت کامل نیست">—</span> : formatMoney(p.cost)}</td>
                  <td className="tabular px-4 py-2.5 text-end">
                    {p.margin === null ? <span className="text-text-subtle">—</span> : <>{formatMoney(p.margin)}<span className={cx('ms-1.5 text-xs', (p.margin_ratio ?? 0) < 0.6 ? 'text-warning' : 'text-text-subtle')}>{formatPercent(p.margin_ratio ?? 0)}</span></>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {data.products.length > limit ? <p className="border-t border-border px-4 py-2.5 text-xs text-text-muted">{formatNumber(limit)} محصول اول نمایش داده شد؛ فهرست کامل در خروجی اکسل است.</p> : null}
      </Card>
      <Card>
        <CardHeader icon={<Boxes />} title="به تفکیک دسته" />
        <div className="p-5"><MoneyShareBar caption="سهم دسته‌ها از فروش" parts={sharesOf(data.categories, (c) => c.name, (c) => c.name, (c) => c.revenue, 5)} /></div>
      </Card>
    </div>
  );
}

/* ------------------------------------------ hours ------------------------------------------ */

export function HoursSection({ data }: { data: HoursReport }) {
  if (data.cells.length === 0) return <Card><EmptyState icon={<Clock />} title="داده‌ای برای این بازه نیست" description="با ثبت سفارش، الگوی روزها و ساعت‌های شلوغ اینجا دیده می‌شود." /></Card>;
  const first = Math.min(data.first_hour, 8);
  const last = Math.max(data.last_hour, 22);
  const cols = Array.from({ length: last - first + 1 }, (_, i) => first + i).map((h) => ({ key: h, label: toPersianDigits(h) }));
  const quiet = data.profile.filter((h) => h.hour >= first && h.hour <= last).sort((a, b) => a.sales - b.sales)[0];

  return (
    <div className="flex flex-col gap-6">
      <StaleNote stale={data.stale} />
      <div className="grid gap-4 sm:grid-cols-2">
        {data.busiest ? (
          <div className="rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
            <p className="text-xs text-text-muted">شلوغ‌ترین زمان</p>
            <p className="mt-1 text-lg font-bold">{WEEK_DAYS[data.busiest.day]}‌ها، ساعت {toPersianDigits(data.busiest.hour)}</p>
            <p className="text-sm text-text-muted">به طور میانگین {formatMoney(data.busiest.sales)} فروش در این ساعت؛ نیروی کافی بچینید.</p>
          </div>
        ) : null}
        {quiet ? (
          <div className="rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
            <p className="text-xs text-text-muted">خلوت‌ترین ساعت کاری</p>
            <p className="mt-1 text-lg font-bold">ساعت {toPersianDigits(quiet.hour)}</p>
            <p className="text-sm text-text-muted">فرصت تخفیف ساعت‌های خلوت یا کاهش شیفت.</p>
          </div>
        ) : null}
      </div>
      <Card>
        <CardHeader icon={<Clock />} title="روز و ساعت" description="میانگین فروش هر روز هفته در هر ساعت، در این بازه" />
        <div className="p-5">
          <MoneyHeatmap caption="فروش بر اساس روز هفته و ساعت" rows={WEEK_DAYS} cols={cols} cells={data.cells.map((c) => ({ row: c.day, col: c.hour, value: c.sales }))} />
        </div>
      </Card>
      <Card>
        <CardHeader title="فروش هر ساعت" description="جمع کل بازه" />
        <div className="p-5">
          <MoneyColumnChart caption="فروش بر اساس ساعت" valueLabel="فروش" height={180}
            data={data.profile.filter((h) => h.hour >= first && h.hour <= last).map((h) => ({ label: toPersianDigits(h.hour), value: h.sales }))} />
        </div>
      </Card>
    </div>
  );
}

/* ----------------------------------------- branches ----------------------------------------- */

export function BranchesSection({ rows, stale }: { rows: BranchRow[]; stale: boolean }) {
  const max = Math.max(...rows.map((b) => b.sales), 1);

  return (
    <div className="flex flex-col gap-6">
      <StaleNote stale={stale} />
      {rows.length < 2 ? <Alert tone="info">فقط یک شعبه دارید؛ مقایسه‌ی شعبه‌ها با افزودن شعبه‌ی دوم معنا پیدا می‌کند.</Alert> : null}
      <Card>
        <CardHeader icon={<Store />} title="مقایسه‌ی شعبه‌ها" />
        <div className="overflow-x-auto">
          <table className="w-full min-w-[48rem] text-sm">
            <thead className="bg-surface-muted/60 text-xs text-text-muted">
              <tr>
                <th className="px-4 py-2.5 text-start font-medium">شعبه</th>
                <th className="w-64 px-4 py-2.5 text-start font-medium">فروش</th>
                <th className="px-4 py-2.5 text-end font-medium">سفارش</th>
                <th className="px-4 py-2.5 text-end font-medium">میانگین</th>
                <th className="px-4 py-2.5 text-end font-medium">مواد + دستمزد</th>
                <th className="px-4 py-2.5 text-end font-medium">هزینه‌ها</th>
                <th className="px-4 py-2.5 text-end font-medium">سود</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((b) => (
                <tr key={b.id}>
                  <td className="px-4 py-3 font-medium">{b.name}</td>
                  <td className="px-4 py-3">
                    <p className="flex justify-between gap-2"><span className="tabular font-medium">{formatMoney(b.sales)}</span><span className="tabular text-xs text-text-subtle">{formatPercent(b.share)}</span></p>
                    <div className="mt-1 h-1.5 rounded-full bg-surface-muted"><div className="h-full rounded-full bg-brand" style={{ width: `${(b.sales / max) * 100}%` }} /></div>
                  </td>
                  <td className="tabular px-4 py-3 text-end">{formatNumber(b.orders)}</td>
                  <td className="tabular px-4 py-3 text-end">{formatMoney(b.average)}</td>
                  <td className="tabular px-4 py-3 text-end text-text-muted">{formatMoney(b.cogs + b.labour)}{b.prime_cost !== null ? <span className="ms-1.5 text-xs">{b.prime_cost > 1 ? '+۱۰۰٪' : formatPercent(b.prime_cost)}</span> : null}</td>
                  <td className="tabular px-4 py-3 text-end text-text-muted">{formatMoney(b.expenses)}</td>
                  <td className={cx('tabular px-4 py-3 text-end font-semibold', b.profit < 0 ? 'text-danger' : '')}>{formatMoney(b.profit)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

/* ----------------------------------------- customers ----------------------------------------- */

export function CustomersSection({ data }: { data: CustomersReport }) {
  return (
    <div className="flex flex-col gap-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="مشتری خریدار" value={formatNumber(data.buyers)} icon={<Users />} hint={data.known_share !== null ? `${formatPercent(data.known_share)} سفارش‌ها با مشتری شناخته‌شده` : undefined} />
        <StatTile label="مشتری جدید" value={formatNumber(data.new)} icon={<UserPlus />} hint="عضو شده در این بازه" />
        <StatTile label="بازگشتی" value={formatNumber(data.returning)} icon={<Crown />} hint={`${formatNumber(data.repeat)} نفر بیش از یک بار در این بازه خریدند`} />
        <StatTile label="میانگین خرید هر مشتری" value={formatMoney(data.average_spend)} icon={<Coins />} />
      </div>
      <Card>
        <CardHeader icon={<Crown />} title="مشتریان برتر" description="بیشترین خرید در این بازه" />
        {data.top.length === 0 ? <EmptyState icon={<Users />} title="خرید با مشتری شناخته‌شده‌ای ثبت نشده" description="وقتی مشتری با شماره‌ی موبایل سفارش دهد، اینجا دیده می‌شود." /> : (
          <ol className="divide-y divide-border">
            {data.top.map((c, i) => (
              <li key={c.id}>
                <Link href={`/dashboard/customers/${c.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-surface-muted/60">
                  <span className={cx('flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold', i < 3 ? 'bg-warning-soft text-warning' : 'bg-surface-muted text-text-muted')}>{toPersianDigits(i + 1)}</span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{c.name ?? 'مشتری بی‌نام'}</span>
                    {c.phone ? <span className="block text-xs text-text-muted" dir="ltr" style={{ textAlign: 'end' }}>{toPersianDigits(c.phone)}</span> : null}
                  </span>
                  <span className="whitespace-nowrap text-xs text-text-muted">{formatNumber(c.orders)} سفارش</span>
                  <span className="tabular whitespace-nowrap text-end font-semibold">{formatMoney(c.spend)}</span>
                </Link>
              </li>
            ))}
          </ol>
        )}
      </Card>
    </div>
  );
}

/* ----------------------------------------- inventory ----------------------------------------- */

export function InventorySection({ data }: { data: InventoryReport }) {
  const qty = (q: number, unit: 'g' | 'ml' | 'pcs') => {
    const fa = (n: number) => new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(n);

    return unit === 'pcs' ? `${fa(q)} عدد` : q >= 1000 ? `${fa(q / 1000)} ${unit === 'g' ? 'کیلو' : 'لیتر'}` : `${fa(q)} ${unit === 'g' ? 'گرم' : 'میلی‌لیتر'}`;
  };

  return (
    <div className="flex flex-col gap-6">
      <StaleNote stale={data.stale} />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="بهای مواد مصرفی" value={formatMoney(data.consumption)} icon={<Boxes />} hint={data.sales > 0 ? `فود کاست ${formatPercent(data.consumption / data.sales)}` : 'طبق دستور پخت'} />
        <StatTile label="ضایعات" value={formatMoney(data.waste)} icon={<Trash2 />} hint={data.consumption > 0 ? `${formatPercent(data.waste / data.consumption)} مصرف` : undefined} />
        <StatTile label="خرید تحویل‌شده" value={formatMoney(data.purchases)} icon={<ShoppingBag />} />
        <StatTile label="فروش" value={formatMoney(data.sales)} icon={<Coins />} />
      </div>
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader icon={<Trash2 />} title="بیشترین ضایعات" />
          {data.waste_items.length === 0 ? <EmptyState icon={<Trash2 />} title="ضایعاتی ثبت نشده" description="ضایعات از صفحه‌ی انبار با «ضایعات / اصلاح» ثبت می‌شود." /> : (
            <ul className="divide-y divide-border">
              {data.waste_items.map((w) => (
                <li key={w.id} className="flex items-center gap-3 px-4 py-3 text-sm">
                  <span className="min-w-0 flex-1 truncate font-medium">{w.name}</span>
                  <span className="text-text-muted">{qty(w.quantity, w.unit)} • {formatNumber(w.entries)} بار</span>
                  <span className="tabular w-28 text-end font-semibold">{formatMoney(w.cost)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <CardHeader icon={<AlertTriangle />} title="دلایل پرتکرار" />
          {data.reasons.length === 0 ? <p className="px-5 py-8 text-center text-sm text-text-muted">دلیلی ثبت نشده.</p> : (
            <ul className="flex flex-col gap-2 p-5 text-sm">
              {data.reasons.map((r) => <li key={r.note} className="flex justify-between gap-2"><span className="truncate">{r.note}</span><span className="text-text-muted">{formatNumber(r.count)} بار</span></li>)}
            </ul>
          )}
        </Card>
      </div>
      <p className="text-xs text-text-muted">مبلغ ضایعات و خرید به بهای میانگین همان لحظه حساب شده است. <Link href="/dashboard/inventory" className="text-brand hover:underline">انبار</Link></p>
    </div>
  );
}

