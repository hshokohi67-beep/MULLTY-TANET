import Link from 'next/link';
import type { ReactNode } from 'react';
import {
  Aperture, Armchair, Bell, Cake, ChefHat, Clock, CreditCard, Crown, Flame, GitBranch, Grid3x3, NotebookPen, ReceiptText, ShoppingBag, Store, Tags, Target, Timer, UserX, Users, Wallet, XCircle,
} from 'lucide-react';
import { Badge, Card, CardHeader, EmptyState, StatTile } from '@cafe/ui';
import { formatMoney, formatMoneyCompact, formatNumber, formatPercent, JALALI_MONTHS, toPersianDigits } from '@cafe/locale';
import { MoneyColumnChart, MoneyHeatmap, MoneyShareBar } from '@/components/MoneyCharts';
import { GoalEditor, ShiftNotes, type Note } from './WidgetClients';

/* ------------------------------- data shapes (from the API) ------------------------------- */

interface Sales { sales: number; orders: number; average: number; discounts: number }
export interface OverviewData {
  kpis?: { current: Sales; compare: { yesterday?: Sales; last_week?: Sales; previous?: Sales }; cancelled: number };
  series?: { unit: 'hour' | 'day'; points: { label: string; value: number; reference: number }[] };
  trend?: number[];
  live?: { new: number; preparing: number; ready: number; out_for_delivery: number; late: number; table_calls: number; kitchen_queue: number; avg_prep_minutes: number | null };
  top_products?: { name: string; quantity: number; revenue: number }[];
  payment_mix?: { method: string; label: string; amount: number }[];
  customers?: { new: number; buyers: number; returning: number; birthdays: { id: string; name: string | null; month: number; day: number; in_days: number }[] };
  alerts: { type: string; severity: 'danger' | 'warning' | 'info'; title: string; count: number; href: string }[];
}

export interface WidgetContext {
  range: string;
  rangeLabel: string;
  isToday: boolean;
  userId: string;
  can: (permission: string) => boolean;
}

const pct = (v: number) => formatPercent(v, 0);
const ratio = (now: number, before: number | undefined) => (before === undefined || before === 0 ? null : (now - before) / before);
const dayLabel = (date: string) => new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'short', timeZone: 'Asia/Tehran' }).format(new Date(`${date}T12:00:00Z`));
const link = (href: string, text: string) => <Link href={href} className="text-sm text-brand hover:underline">{text}</Link>;

function Shell({ icon, title, description, actions, children }: { icon: ReactNode; title: string; description?: string; actions?: ReactNode; children: ReactNode }) {
  return (
    <Card className="h-full">
      <CardHeader icon={icon} title={title} description={description} actions={actions} />
      {children}
    </Card>
  );
}

function Empty({ text }: { text: string }) {
  return <p className="px-5 py-8 text-center text-sm text-text-muted">{text}</p>;
}

/* ------------------------------------ core widgets ------------------------------------ */

export function KpisWidget({ data, ctx }: { data: OverviewData; ctx: WidgetContext }) {
  const k = data.kpis;
  if (!k) return null;
  const base = ctx.isToday ? k.compare.yesterday : k.compare.previous;
  const against = ctx.isToday ? 'نسبت به دیروز همین ساعت' : 'نسبت به دوره‌ی قبل';

  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatTile label={`فروش ${ctx.rangeLabel}`} value={formatMoney(k.current.sales)} icon={<Wallet />} delta={{ ratio: ratio(k.current.sales, base?.sales), against }} trend={data.trend} />
      <StatTile label="تعداد سفارش" value={formatNumber(k.current.orders)} icon={<ReceiptText />} delta={{ ratio: ratio(k.current.orders, base?.orders), against }} />
      <StatTile label="میانگین هر سفارش" value={formatMoney(k.current.average)} icon={<ShoppingBag />} delta={{ ratio: ratio(k.current.average, base?.average), against }} />
      <StatTile
        label="لغو یا رد شده"
        value={formatNumber(k.cancelled)}
        icon={<XCircle />}
        hint={ctx.isToday && k.compare.last_week ? `هفته‌ی پیش همین روز تا این ساعت: ${formatMoney(k.compare.last_week.sales)} فروش` : `${formatMoney(k.current.discounts)} تخفیف داده شد`}
      />
    </div>
  );
}

export function SalesChartWidget({ data, ctx }: { data: OverviewData; ctx: WidgetContext }) {
  const s = data.series;
  if (!s) return null;
  const hourly = s.unit === 'hour';

  return (
    <Shell icon={<Clock />} title={hourly ? 'فروش ساعت‌به‌ساعت' : 'فروش روزانه'} description={hourly ? 'در مقایسه با میانگین همین روزِ هفته در ۴ هفته‌ی گذشته' : 'در مقایسه با همان روز در دوره‌ی قبل'}>
      <div className="p-5">
        {s.points.every((p) => p.value === 0 && p.reference === 0) ? (
          <EmptyState icon={<Clock />} title="هنوز فروشی ثبت نشده" description="با اولین سفارش، نمودار این بخش پر می‌شود." />
        ) : (
          <MoneyColumnChart
            caption={hourly ? 'فروش هر ساعت و میانگین معمول' : 'فروش هر روز و دوره‌ی قبل'}
            data={s.points.map((p) => ({ label: hourly ? toPersianDigits(p.label) : dayLabel(p.label), value: p.value, reference: p.reference }))}
            valueLabel={hourly ? ctx.rangeLabel : 'این دوره'}
            referenceLabel={hourly ? 'روز معمول' : 'دوره‌ی قبل'}
          />
        )}
      </div>
    </Shell>
  );
}

export function LiveWidget({ data }: { data: OverviewData }) {
  const l = data.live;
  if (!l) return null;

  return (
    <Shell icon={<ChefHat />} title="همین حالا" description="وضعیت زنده‌ی سفارش‌ها و آشپزخانه" actions={link('/dashboard/orders', 'سفارش‌ها')}>
      <div className="grid grid-cols-3 gap-2 p-5">
        {[
          { label: 'جدید', value: l.new, tone: 'bg-info-soft text-info' },
          { label: 'در دست آماده‌سازی', value: l.preparing, tone: 'bg-warning-soft text-warning' },
          { label: 'آماده', value: l.ready, tone: 'bg-success-soft text-success' },
        ].map((s) => (
          <div key={s.label} className={`rounded-xl px-3 py-3 text-center ${s.tone}`}>
            <p className="text-2xl font-bold">{formatNumber(s.value)}</p>
            <p className="text-xs">{s.label}</p>
          </div>
        ))}
      </div>
      <ul className="divide-y divide-border border-t border-border text-sm">
        <li className="flex items-center justify-between px-5 py-2.5"><span className="flex items-center gap-2 text-text-muted"><Timer className="size-4" aria-hidden="true" />سفارش دیرکرده (+۱۵ دقیقه)</span>{l.late > 0 ? <Badge tone="danger" dot>{formatNumber(l.late)}</Badge> : <Badge tone="success">ندارد</Badge>}</li>
        <li className="flex items-center justify-between px-5 py-2.5"><span className="flex items-center gap-2 text-text-muted"><Bell className="size-4" aria-hidden="true" />درخواست میزها</span><span className="font-semibold">{formatNumber(l.table_calls)}</span></li>
        <li className="flex items-center justify-between px-5 py-2.5"><span className="flex items-center gap-2 text-text-muted"><ChefHat className="size-4" aria-hidden="true" />آیتم در صف آشپزخانه</span><span className="font-semibold">{formatNumber(l.kitchen_queue)}</span></li>
        <li className="flex items-center justify-between px-5 py-2.5"><span className="flex items-center gap-2 text-text-muted"><Clock className="size-4" aria-hidden="true" />میانگین زمان آماده‌سازی امروز</span><span className="font-semibold">{l.avg_prep_minutes === null ? '—' : `${formatNumber(l.avg_prep_minutes)} دقیقه`}</span></li>
      </ul>
    </Shell>
  );
}

function RankedBars({ rows }: { rows: { key: string; label: string; value: number; right: ReactNode }[] }) {
  const max = Math.max(1, ...rows.map((r) => r.value));

  return (
    <ol className="flex flex-col gap-3 p-5">
      {rows.map((r, i) => (
        <li key={r.key} className="flex flex-col gap-1.5">
          <div className="flex items-center justify-between gap-2 text-sm">
            <span className="flex min-w-0 items-center gap-2"><span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-surface-muted text-[11px] text-text-muted">{formatNumber(i + 1)}</span><span className="truncate">{r.label}</span></span>
            <span className="tabular shrink-0 text-text-muted">{r.right}</span>
          </div>
          <div className="h-1.5 rounded-full bg-surface-muted"><div className="h-full rounded-full bg-viz-1" style={{ width: `${(r.value / max) * 100}%` }} /></div>
        </li>
      ))}
    </ol>
  );
}

export function TopProductsWidget({ data, ctx }: { data: OverviewData; ctx: WidgetContext }) {
  const rows = data.top_products;
  if (!rows) return null;

  return (
    <Shell icon={<Flame />} title="پرفروش‌ها" description={`${ctx.rangeLabel}، بر اساس تعداد`}>
      {rows.length === 0 ? <Empty text="هنوز فروشی نیست." /> : (
        <RankedBars rows={rows.map((p) => ({ key: p.name, label: p.name, value: p.quantity, right: <><strong className="text-text">{formatNumber(p.quantity)}</strong> عدد • {formatMoneyCompact(p.revenue)}</> }))} />
      )}
    </Shell>
  );
}

export function PaymentMixWidget({ data, ctx }: { data: OverviewData; ctx: WidgetContext }) {
  if (!data.payment_mix) return null;

  return (
    <Shell icon={<Wallet />} title="روش‌های پرداخت" description={`${ctx.rangeLabel}، خالص پس از بازگشت وجه`} actions={link('/dashboard/payments', 'پرداخت‌ها')}>
      <div className="p-5">
        <MoneyShareBar caption="سهم هر روش پرداخت" parts={data.payment_mix.map((m) => ({ key: m.method, label: m.label, value: m.amount }))} empty="هنوز پرداختی ثبت نشده." />
      </div>
    </Shell>
  );
}

export function CustomersWidget({ data, ctx }: { data: OverviewData; ctx: WidgetContext }) {
  const c = data.customers;
  if (!c) return null;

  return (
    <Shell icon={<Users />} title="مشتریان" description={ctx.rangeLabel} actions={link('/dashboard/customers', 'همه')}>
      <div className="grid grid-cols-2 gap-3 p-5 pb-3">
        <div className="rounded-xl bg-surface-muted px-3 py-3"><p className="text-xl font-bold">{formatNumber(c.new)}</p><p className="text-xs text-text-muted">عضو تازه</p></div>
        <div className="rounded-xl bg-surface-muted px-3 py-3"><p className="text-xl font-bold">{c.buyers > 0 ? pct(c.returning / c.buyers) : '—'}</p><p className="text-xs text-text-muted">خریدار قدیمی از {formatNumber(c.buyers)} خریدار عضو</p></div>
      </div>
      <div className="px-5 pb-5">
        <p className="mb-2 flex items-center gap-2 text-sm font-medium"><Cake className="size-4 text-accent" aria-hidden="true" />تولدهای ۷ روز آینده</p>
        {c.birthdays.length === 0 ? <p className="text-sm text-text-muted">تولدی در این هفته نیست.</p> : (
          <ul className="flex flex-col gap-1.5">
            {c.birthdays.slice(0, 5).map((b) => (
              <li key={b.id}>
                <Link href={`/dashboard/customers/${b.id}`} className="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-surface-muted">
                  <span>{b.name ?? 'مشتری'}</span>
                  <span className="text-xs text-text-muted">{b.in_days === 0 ? <Badge tone="accent">امروز</Badge> : `${toPersianDigits(b.day)} ${JALALI_MONTHS[b.month - 1]}`}</span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Shell>
  );
}

/* ------------------------------------ new widgets ------------------------------------ */

function Progress({ value, goal, label }: { value: number; goal: number; label: string }) {
  const done = goal > 0 ? Math.min(1, value / goal) : 0;

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-baseline justify-between gap-2 text-sm">
        <span className="text-text-muted">{label}</span>
        <span><strong>{formatMoneyCompact(value)}</strong> <span className="text-xs text-text-muted">از {formatMoneyCompact(goal)}</span></span>
      </div>
      <div className="h-2.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(done * 100)} aria-label={label}>
        <div className={`h-full rounded-full transition-[width] duration-[var(--duration-slow)] ${done >= 1 ? 'bg-success' : 'bg-viz-1'}`} style={{ width: `${done * 100}%` }} />
      </div>
      <p className="text-xs text-text-subtle">{done >= 1 ? 'به هدف رسیدید 🎉' : `${pct(done)} انجام شده`}</p>
    </div>
  );
}

export function GoalWidget({ data, ctx }: { data: { daily_goal: number; today: number; monthly_goal: number; month_to_date: number; projection: number; days_left: number }; ctx: WidgetContext }) {
  const hasGoal = data.daily_goal > 0 || data.monthly_goal > 0;

  return (
    <Shell icon={<Target />} title="هدف فروش" actions={ctx.can('settings.update') ? <GoalEditor daily={data.daily_goal} monthly={data.monthly_goal} /> : undefined}>
      <div className="flex flex-col gap-5 p-5">
        {!hasGoal ? (
          <p className="text-sm text-text-muted">هنوز هدفی تعیین نشده. با «تعیین هدف»، پیشرفت روزانه و ماهانه را اینجا ببینید.</p>
        ) : (
          <>
            {data.daily_goal > 0 ? <Progress value={data.today} goal={data.daily_goal} label="امروز" /> : null}
            {data.monthly_goal > 0 ? (
              <div className="flex flex-col gap-2">
                <Progress value={data.month_to_date} goal={data.monthly_goal} label="این ماه" />
                <p className="rounded-lg bg-surface-muted px-3 py-2 text-xs text-text-muted">
                  با همین روند، فروش این ماه به حدود <strong className="text-text">{formatMoneyCompact(data.projection)}</strong> می‌رسد
                  {data.projection >= data.monthly_goal ? ' — بالاتر از هدف.' : ` — ${formatMoneyCompact(data.monthly_goal - data.projection)} کمتر از هدف، ${formatNumber(data.days_left)} روز مانده.`}
                </p>
              </div>
            ) : null}
          </>
        )}
      </div>
    </Shell>
  );
}

export function ChannelMixWidget({ data, ctx }: { data: { channels: { type: string; label: string; sales: number; orders: number }[] }; ctx: WidgetContext }) {
  const total = data.channels.reduce((s, c) => s + c.sales, 0);

  return (
    <Shell icon={<GitBranch />} title="کانال‌های فروش" description={ctx.rangeLabel}>
      {data.channels.length === 0 ? <Empty text="هنوز فروشی نیست." /> : (
        <RankedBars rows={data.channels.map((c) => ({ key: c.type, label: c.label, value: c.sales, right: <><strong className="text-text">{formatMoneyCompact(c.sales)}</strong> • {total > 0 ? pct(c.sales / total) : '—'} • {formatNumber(c.orders)} سفارش</> }))} />
      )}
    </Shell>
  );
}

const WEEK = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

export function HeatmapWidget({ data }: { data: { cells: { day: number; hour: number; sales: number }[]; first_hour: number; last_hour: number } }) {
  // Always show a normal day's span (8–22) so a few busy hours don't stretch into giant cells.
  const first = Math.min(data.first_hour, 8);
  const last = Math.max(data.last_hour, 22);
  const cols = Array.from({ length: last - first + 1 }, (_, i) => first + i).map((h) => ({ key: h, label: toPersianDigits(h) }));

  return (
    <Shell icon={<Grid3x3 />} title="ساعت‌های شلوغ هفته" description="میانگین فروش هر روز و ساعت در ۴ هفته‌ی اخیر؛ برای برنامه‌ریزی شیفت و تخفیف ساعت‌های خلوت">
      <div className="p-5">
        {data.cells.length === 0 ? <EmptyState icon={<Grid3x3 />} title="داده‌ی کافی نیست" description="بعد از چند روز فروش، الگوی ساعت‌های شلوغ اینجا دیده می‌شود." /> : (
          <MoneyHeatmap caption="فروش بر اساس روز هفته و ساعت" rows={WEEK} cols={cols} cells={data.cells.map((c) => ({ row: c.day, col: c.hour, value: c.sales }))} />
        )}
      </div>
    </Shell>
  );
}

export function TablesNowWidget({ data }: { data: { tables: number; occupied: number; seats: number; avg_sitting_minutes: number | null; dine_in_sales: number; sales_per_seat: number } }) {
  const share = data.tables > 0 ? data.occupied / data.tables : 0;

  return (
    <Shell icon={<Armchair />} title="وضعیت میزها" actions={link('/dashboard/tables', 'میزها')}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <p className="text-3xl font-bold">{formatNumber(data.occupied)}<span className="text-base font-normal text-text-muted"> از {formatNumber(data.tables)} میز پر است</span></p>
          <div className="mt-2 h-2 overflow-hidden rounded-full bg-surface-muted"><div className="h-full rounded-full bg-viz-1" style={{ width: `${share * 100}%` }} /></div>
        </div>
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <div className="rounded-lg bg-surface-muted px-3 py-2"><dt className="text-xs text-text-muted">میانگین مدت نشستن امروز</dt><dd className="font-semibold">{data.avg_sitting_minutes === null ? '—' : `${formatNumber(data.avg_sitting_minutes)} دقیقه`}</dd></div>
          <div className="rounded-lg bg-surface-muted px-3 py-2"><dt className="text-xs text-text-muted">فروش سالن به ازای هر صندلی</dt><dd className="font-semibold">{formatMoneyCompact(data.sales_per_seat)}</dd></div>
        </dl>
      </div>
    </Shell>
  );
}

export function KitchenSpeedWidget({ data, ctx }: { data: { stations: { name: string; items: number; avg_minutes: number | null; late_share: number | null }[] }; ctx: WidgetContext }) {
  return (
    <Shell icon={<ChefHat />} title="سرعت آشپزخانه" description={ctx.rangeLabel}>
      {data.stations.length === 0 ? <Empty text="هنوز آیتمی در آشپزخانه آماده نشده." /> : (
        <ul className="divide-y divide-border">
          {data.stations.map((s) => (
            <li key={s.name} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
              <span><span className="font-medium">{s.name}</span><span className="block text-xs text-text-muted">{formatNumber(s.items)} آیتم</span></span>
              <span className="text-end">
                <span className="block font-semibold">{s.avg_minutes === null ? '—' : `${formatNumber(s.avg_minutes)} دقیقه`}</span>
                {s.late_share !== null ? <Badge tone={s.late_share > 0.2 ? 'danger' : s.late_share > 0.05 ? 'warning' : 'success'} dot>{pct(s.late_share)} دیرکرد</Badge> : null}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Shell>
  );
}

export function CancellationsWidget({ data, ctx }: { data: { cancelled: number; rejected: number; total_orders: number; reasons: { reason: string; count: number }[] }; ctx: WidgetContext }) {
  const lost = data.cancelled + data.rejected;

  return (
    <Shell icon={<XCircle />} title="لغوها" description={ctx.rangeLabel}>
      <div className="flex flex-col gap-4 p-5">
        <div className="grid grid-cols-3 gap-2 text-center">
          <div className="rounded-xl bg-surface-muted px-2 py-2"><p className="text-lg font-bold">{formatNumber(data.cancelled)}</p><p className="text-xs text-text-muted">لغو</p></div>
          <div className="rounded-xl bg-surface-muted px-2 py-2"><p className="text-lg font-bold">{formatNumber(data.rejected)}</p><p className="text-xs text-text-muted">رد</p></div>
          <div className="rounded-xl bg-surface-muted px-2 py-2"><p className="text-lg font-bold">{data.total_orders > 0 ? pct(lost / data.total_orders) : '—'}</p><p className="text-xs text-text-muted">از کل سفارش‌ها</p></div>
        </div>
        {data.reasons.length > 0 ? (
          <ul className="flex flex-col gap-1.5 text-sm">
            {data.reasons.map((r) => <li key={r.reason} className="flex justify-between gap-2"><span className="truncate text-text-muted">{r.reason}</span><span className="font-medium">{formatNumber(r.count)}</span></li>)}
          </ul>
        ) : <p className="text-sm text-text-muted">لغوی ثبت نشده. 👌</p>}
      </div>
    </Shell>
  );
}

export function BranchesWidget({ data, ctx }: { data: { branches: { id: string; name: string; sales: number; orders: number; average: number }[] }; ctx: WidgetContext }) {
  return (
    <Shell icon={<Store />} title="مقایسه‌ی شعبه‌ها" description={ctx.rangeLabel}>
      {data.branches.length < 2 ? <Empty text="این بخش برای کافه‌های چندشعبه است." /> : (
        <RankedBars rows={data.branches.map((b) => ({ key: b.id, label: b.name, value: b.sales, right: <><strong className="text-text">{formatMoneyCompact(b.sales)}</strong> • {formatNumber(b.orders)} سفارش • میانگین {formatMoneyCompact(b.average)}</> }))} />
      )}
    </Shell>
  );
}

export function PaymentHealthWidget({ data, ctx }: { data: { attempts: number; paid: number; failed: number; expired: number; pending: number; success_rate: number | null; abandoned_orders: number }; ctx: WidgetContext }) {
  return (
    <Shell icon={<CreditCard />} title="سلامت پرداخت آنلاین" description={ctx.rangeLabel}>
      {data.attempts === 0 ? <Empty text="در این بازه پرداخت آنلاینی انجام نشده." /> : (
        <div className="flex flex-col gap-4 p-5">
          <div className="flex items-end justify-between">
            <p className="text-3xl font-bold">{data.success_rate === null ? '—' : pct(data.success_rate)}</p>
            <Badge tone={data.success_rate === null ? 'neutral' : data.success_rate >= 0.8 ? 'success' : data.success_rate >= 0.6 ? 'warning' : 'danger'} dot>نرخ موفقیت درگاه</Badge>
          </div>
          <dl className="grid grid-cols-2 gap-2 text-sm">
            {[['موفق', data.paid], ['ناموفق', data.failed], ['منقضی', data.expired], ['سفارش رهاشده', data.abandoned_orders]].map(([label, value]) => (
              <div key={label} className="flex justify-between rounded-lg bg-surface-muted px-3 py-2"><dt className="text-text-muted">{label}</dt><dd className="font-semibold">{formatNumber(Number(value))}</dd></div>
            ))}
          </dl>
        </div>
      )}
    </Shell>
  );
}

export function AtRiskWidget({ data }: { data: { regulars: number; typical_gap_days: number | null; at_risk: number; customers: { customer_id: string; name: string | null; orders: number; days_since: number; usual_gap: number }[] } }) {
  return (
    <Shell icon={<UserX />} title="مشتریان در خطر رفتن" description={data.typical_gap_days ? `مشتریان ثابت معمولاً هر ${formatNumber(data.typical_gap_days)} روز برمی‌گردند` : 'مشتریانی که ۳ بار یا بیشتر خرید کرده‌اند'}>
      {data.customers.length === 0 ? <Empty text={data.regulars === 0 ? 'هنوز مشتری ثابتی شکل نگرفته.' : 'همه‌ی مشتریان ثابت به‌موقع برمی‌گردند. 👏'} /> : (
        <ul className="divide-y divide-border">
          {data.customers.map((c) => (
            <li key={c.customer_id}>
              <Link href={`/dashboard/customers/${c.customer_id}`} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm hover:bg-surface-muted">
                <span><span className="font-medium">{c.name ?? 'مشتری'}</span><span className="block text-xs text-text-muted">{formatNumber(c.orders)} خرید • معمولاً هر {formatNumber(c.usual_gap)} روز</span></span>
                <Badge tone="warning">{formatNumber(c.days_since)} روز نیامده</Badge>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Shell>
  );
}

export function ClubLiabilityWidget({ data, ctx }: { data: { wallet_owed: number; wallets: number; points: number; points_value: number; cashback_issued: number; spent_from_wallet: number }; ctx: WidgetContext }) {
  return (
    <Shell icon={<Crown />} title="بدهی باشگاه" description="پولی که کافه به اعضا بدهکار است" actions={ctx.can('loyalty.manage') ? link('/dashboard/club', 'باشگاه') : undefined}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <p className="text-2xl font-bold">{formatMoney(data.wallet_owed + data.points_value)}</p>
          <p className="text-xs text-text-muted">{formatMoney(data.wallet_owed)} در کیف پول {formatNumber(data.wallets)} عضو + ارزش {formatNumber(data.points)} امتیاز</p>
        </div>
        <dl className="grid grid-cols-2 gap-2 text-sm">
          <div className="rounded-lg bg-surface-muted px-3 py-2"><dt className="text-xs text-text-muted">هدیه و کش‌بک ({ctx.rangeLabel})</dt><dd className="font-semibold">{formatMoneyCompact(data.cashback_issued)}</dd></div>
          <div className="rounded-lg bg-surface-muted px-3 py-2"><dt className="text-xs text-text-muted">خرج‌شده از کیف پول</dt><dd className="font-semibold">{formatMoneyCompact(data.spent_from_wallet)}</dd></div>
        </dl>
      </div>
    </Shell>
  );
}

export function DiscountsWidget({ data, ctx }: { data: { discounts: { name: string; code: string | null; uses: number; given: number; revenue: number }[] }; ctx: WidgetContext }) {
  return (
    <Shell icon={<Tags />} title="عملکرد تخفیف‌ها" description={ctx.rangeLabel} actions={link('/dashboard/discounts', 'تخفیف‌ها')}>
      {data.discounts.length === 0 ? <Empty text="در این بازه تخفیفی استفاده نشده." /> : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border text-xs text-text-muted"><tr><th className="px-5 py-2 text-start font-medium">تخفیف</th><th className="px-3 py-2 text-start font-medium">استفاده</th><th className="px-3 py-2 text-start font-medium">مبلغ تخفیف</th><th className="px-5 py-2 text-start font-medium">فروش</th></tr></thead>
            <tbody className="tabular divide-y divide-border">
              {data.discounts.map((d) => (
                <tr key={d.name}>
                  <td className="px-5 py-2.5">{d.name}{d.code ? <span className="ms-1.5 text-xs text-text-muted" dir="ltr">{d.code}</span> : null}</td>
                  <td className="px-3 py-2.5">{formatNumber(d.uses)}</td>
                  <td className="px-3 py-2.5">{formatMoneyCompact(d.given)}</td>
                  <td className="px-5 py-2.5 font-medium">{formatMoneyCompact(d.revenue)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Shell>
  );
}

export function ShiftNotesWidget({ data, ctx }: { data: { notes: Note[] }; ctx: WidgetContext }) {
  return (
    <Shell icon={<NotebookPen />} title="یادداشت شیفت" description="برای همکاران شیفت بعد">
      <div className="p-5"><ShiftNotes notes={data.notes} userId={ctx.userId} canModerate={ctx.can('team.manage')} /></div>
    </Shell>
  );
}

interface StoryRow { id: string; thumb_url: string; caption: string | null; status: string; views: number; clicks: number; ends_at: string }

export function StoriesWidget({ data }: { data: { live: number; stories: StoryRow[] } }) {
  return (
    <Shell icon={<Aperture />} title="استوری‌ها" description={data.live ? `${formatNumber(data.live)} استوری در حال نمایش` : 'استوری فعالی نیست'}
      actions={<Link href="/dashboard/stories" className="text-xs font-medium text-brand hover:underline">مدیریت</Link>}>
      {data.stories.length === 0 ? <Empty text="در هفته‌ی اخیر استوری نداشته‌اید؛ یک عکس از محصول تازه بگذارید." /> : (
        <ul className="divide-y divide-border px-5 pb-3">
          {data.stories.map((s) => (
            <li key={s.id} className="flex items-center gap-3 py-2.5">
              {/* eslint-disable-next-line @next/next/no-img-element -- tenant media thumbnail */}
              <img src={s.thumb_url} alt="" className={`size-10 rounded-full object-cover ring-2 ${s.status === 'live' ? 'ring-brand' : 'ring-border'}`} />
              <span className="min-w-0 flex-1 truncate text-sm">{s.caption ?? 'بدون متن'}</span>
              <span className="tabular text-xs text-text-muted">{formatNumber(s.views)} بازدید</span>
              <span className="tabular w-16 text-end text-xs font-semibold">{s.views ? formatPercent(s.clicks / s.views) : '—'}</span>
            </li>
          ))}
        </ul>
      )}
    </Shell>
  );
}
