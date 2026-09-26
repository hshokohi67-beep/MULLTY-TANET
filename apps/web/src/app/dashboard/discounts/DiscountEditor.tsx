'use client';

import { useActionState, useMemo, useState, useTransition } from 'react';
import { Store } from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, ClockSelect, cx, Ltr, SelectField, TextField } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney, formatNumber, formatPercent, IRANIAN_WEEK, normalizeForSearch, toPersianDigits, todayIn, WEEKDAY_LABELS } from '@cafe/locale';
import { deleteDiscount, saveDiscount } from '@/app/actions/commerce';
import { FormStatus } from '@/components/FormStatus';
import { JalaliDateField } from '@/components/JalaliDateField';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import { isoToLocal, localToIso } from '@/lib/operations-types';
import type { Discount, FormState } from '@/lib/types';

export interface DiscountCatalog {
  products: { id: string; name: string }[];
  categories: { id: string; name: string }[];
  timezone: string;
}

const targetsOf = (d: Discount | undefined, type: string) => (d?.rules ?? []).filter((r) => r.type === type).map((r) => r.target);

function summary(d: Discount): string {
  const amount = d.kind === 'percent' ? formatPercent(d.value / 10000, 1) : formatMoney(d.value);
  const parts = [`${amount} تخفیف`];
  if (d.min_order > 0) parts.push(`برای خرید بالای ${formatMoney(d.min_order)}`);
  if (d.max_discount) parts.push(`حداکثر ${formatMoney(d.max_discount)}`);
  if (d.schedule?.from && d.schedule.to) parts.push(`ساعت ${toPersianDigits(d.schedule.from)} تا ${toPersianDigits(d.schedule.to)}`);
  if (d.schedule?.weekdays?.length) parts.push(IRANIAN_WEEK.filter((w) => d.schedule?.weekdays?.includes(w)).map((w) => WEEKDAY_LABELS[w]).join('، '));
  if (d.applies_to === 'items') {
    const count = targetsOf(d, 'product').length + targetsOf(d, 'category').length;
    parts.push(`روی ${formatNumber(count)} محصول یا دسته`);
  }
  if (d.ends_at) parts.push(`تا ${formatJalaliDateTime(d.ends_at)}`);

  return parts.join(' • ');
}

/** Which products and categories an item discount covers: category chips and a searchable product list. */
function Targets({ catalog, products, categories, onProducts, onCategories, error }: {
  catalog: DiscountCatalog;
  products: string[];
  categories: string[];
  onProducts: (ids: string[]) => void;
  onCategories: (ids: string[]) => void;
  error?: string;
}) {
  const [query, setQuery] = useState('');
  const shown = useMemo(() => {
    const q = normalizeForSearch(query.trim());

    return q ? catalog.products.filter((p) => normalizeForSearch(p.name).includes(q)) : catalog.products;
  }, [catalog.products, query]);
  const toggle = (list: string[], id: string) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]);

  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-border p-3.5">
      {catalog.categories.length ? (
        <fieldset>
          <legend className="mb-1.5 text-sm font-medium">دسته‌ها <span className="font-normal text-text-muted">(همه‌ی محصولات آن دسته)</span></legend>
          <div className="flex flex-wrap gap-1.5">
            {catalog.categories.map((c) => (
              <button key={c.id} type="button" aria-pressed={categories.includes(c.id)} onClick={() => onCategories(toggle(categories, c.id))}
                className={cx('rounded-full border px-3 py-1 text-sm transition-colors', categories.includes(c.id) ? 'border-brand bg-brand-soft font-semibold' : 'border-border hover:border-border-strong')}>
                {c.name}
              </button>
            ))}
          </div>
        </fieldset>
      ) : null}
      <fieldset>
        <legend className="mb-1.5 text-sm font-medium">محصولات {products.length ? <span className="font-normal text-text-muted">({formatNumber(products.length)} انتخاب شده)</span> : null}</legend>
        <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="جست‌وجوی محصول…" aria-label="جست‌وجوی محصول"
          className="mb-2 h-10 w-full rounded-xl border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        <ul className="grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
          {shown.map((p) => (
            <li key={p.id}>
              <label className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-muted has-[:checked]:bg-brand-soft">
                <input type="checkbox" checked={products.includes(p.id)} onChange={() => onProducts(toggle(products, p.id))} className="accent-[var(--color-brand)]" />
                <span className="truncate">{p.name}</span>
              </label>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-2 py-3 text-sm text-text-muted">محصولی با این نام نیست.</li> : null}
        </ul>
      </fieldset>
      {error ? <p role="alert" className="text-xs text-danger">{error}</p> : null}
    </div>
  );
}

export function DiscountEditor({ discount, tiers = [], catalog }: { discount?: Discount; tiers?: { id: string; name: string }[]; catalog: DiscountCatalog }) {
  const [open, setOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveDiscount.bind(null, discount?.id ?? null), { ok: false });
  const [kind, setKind] = useState<'percent' | 'fixed'>(discount?.kind ?? 'percent');
  const [timed, setTimed] = useState(Boolean(discount?.schedule?.from));
  const [from, setFrom] = useState(discount?.schedule?.from ?? '15:00');
  const [to, setTo] = useState(discount?.schedule?.to ?? '18:00');
  const [deleting, startDelete] = useTransition();
  const [onMenu, setOnMenu] = useState(discount?.show_on_menu ?? false);
  const [appliesTo, setAppliesTo] = useState<'order' | 'items'>(discount?.applies_to ?? 'order');
  const [productIds, setProductIds] = useState(() => targetsOf(discount, 'product'));
  const [categoryIds, setCategoryIds] = useState(() => targetsOf(discount, 'category'));
  const endLocal = discount?.ends_at ? isoToLocal(discount.ends_at, catalog.timezone) : null;
  const [hasEnd, setHasEnd] = useState(endLocal !== null);
  const [endDate, setEndDate] = useState(endLocal?.date ?? todayIn(catalog.timezone));
  const [endTime, setEndTime] = useState(endLocal?.time ?? '23:45');
  const e = state.errors ?? {};
  const items = onMenu || appliesTo === 'items';

  if (!discount && !creating) {
    return <div><Button onClick={() => setCreating(true)}>تخفیف جدید</Button></div>;
  }

  if (discount && !open) {
    return (
      <Card>
        <CardHeader
          title={discount.name}
          description={summary(discount)}
          actions={<>
            {discount.code ? <Badge tone="brand"><Ltr>{discount.code}</Ltr></Badge> : <Badge tone="info">خودکار</Badge>}
            {discount.show_on_menu ? <Badge tone="success">روی منو</Badge> : null}
            {discount.rules?.some((r) => r.type === 'tier') ? <Badge tone="warning">ویژه‌ی {tiers.find((t) => t.id === discount.rules?.find((r) => r.type === 'tier')?.target)?.name ?? 'سطح'}</Badge> : null}
            {!discount.is_active ? <Badge>غیرفعال</Badge> : null}
            <span className="text-xs text-text-muted">{formatNumber(discount.used_count)} بار استفاده</span>
            <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>
          </>}
        />
      </Card>
    );
  }

  const defaultValue = discount ? (discount.kind === 'percent' ? toPersianDigits(String(discount.value / 100)) : rialToTomanInput(discount.value)) : '';

  return (
    <Card>
      <CardHeader title={discount ? `ویرایش «${discount.name}»` : 'تخفیف جدید'} />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />

        <label className={cx('flex cursor-pointer items-start gap-3 rounded-2xl border p-3.5 transition-colors', onMenu ? 'border-brand bg-brand-soft' : 'border-border')}>
          <input type="checkbox" name="show_on_menu" checked={onMenu} onChange={(ev) => setOnMenu(ev.target.checked)} className="mt-1 accent-[var(--color-brand)]" />
          <span className="flex flex-col gap-0.5">
            <span className="flex items-center gap-1.5 font-semibold"><Store className="size-4 text-brand" aria-hidden="true" />نمایش روی منو</span>
            <span className="text-sm text-text-muted">
              قیمت خط‌خورده و قیمت تازه روی کارت محصولات، و در اسلایدر «پیشنهاد امروز و تخفیف‌ها» با زمان باقی‌مانده. مشتری همان قیمتی را می‌پردازد که روی کارت دیده است، حتی اگر کد تخفیف هم وارد کند.
            </span>
          </span>
        </label>
        {onMenu ? (
          <Alert tone="info">
            تخفیفِ روی منو برای همه قطعی است: بدون کد، بدون حداقل خرید و سقف، و مخصوص مشتری یا سطح خاصی نیست. محصول یا دسته را پایین انتخاب کنید؛ برای نمایش زمان باقی‌مانده، «تا تاریخ مشخص» یا ساعت‌ها را تعیین کنید.
            {e.show_on_menu ? <span className="mt-1 block font-semibold text-danger">{e.show_on_menu}</span> : null}
          </Alert>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-3">
          <TextField label="نام" name="name" defaultValue={discount?.name} required placeholder={onMenu ? 'مثلاً جشنواره‌ی پاییز' : 'مثلاً ساعت خوش'} hint={onMenu ? 'روی کارت و در سبد خرید دیده می‌شود.' : undefined} error={e.name} />
          {onMenu ? null : <TextField label="کد تخفیف" name="code" defaultValue={discount?.code ?? ''} ltr placeholder="خالی = خودکار" hint="فقط حروف انگلیسی و عدد، مثل YALDA" error={e.code} />}
          <SelectField label="نوع" name="kind" value={kind} onChange={(ev) => setKind(ev.target.value as 'percent' | 'fixed')} error={e.kind}>
            <option value="percent">درصدی</option>
            <option value="fixed">{onMenu ? 'مبلغ ثابت از هر عدد' : 'مبلغ ثابت'}</option>
          </SelectField>
          {kind === 'percent' ? (
            <TextField key="percent" label="درصد" name="value" defaultValue={discount?.kind === 'percent' ? defaultValue : ''} inputMode="decimal" ltr required error={e.value} />
          ) : (
            <MoneyField key="fixed" label="مبلغ (تومان)" name="value" defaultValue={discount?.kind === 'fixed' ? defaultValue : ''} required error={e.value} />
          )}
          {onMenu ? null : <>
            <MoneyField label="حداقل خرید (تومان)" name="min_order" defaultValue={rialToTomanInput(discount?.min_order ?? 0)} error={e.min_order} />
            {kind === 'percent' ? (
              <MoneyField label="سقف تخفیف (تومان)" name="max_discount" defaultValue={rialToTomanInput(discount?.max_discount)} hint="خالی = بدون سقف" error={e.max_discount} />
            ) : <span />}
            <TextField label="سقف کل استفاده" name="usage_limit" defaultValue={discount?.usage_limit != null ? String(discount.usage_limit) : ''} inputMode="numeric" ltr hint="خالی = نامحدود" error={e.usage_limit} />
            <TextField label="سقف استفاده‌ی هر مشتری" name="per_customer_limit" defaultValue={discount?.per_customer_limit != null ? String(discount.per_customer_limit) : ''} inputMode="numeric" ltr hint="نیاز به ورود مشتری دارد" error={e.per_customer_limit} />
          </>}
        </div>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-sm font-medium">روی چه چیزی؟</legend>
          <div className="flex flex-wrap gap-2">
            {([['order', 'کل سفارش'], ['items', 'محصولات یا دسته‌های مشخص']] as const).map(([key, label]) => (
              <button key={key} type="button" aria-pressed={(items ? 'items' : 'order') === key} disabled={onMenu && key === 'order'} onClick={() => setAppliesTo(key)}
                className={cx('rounded-xl border px-3.5 py-2 text-sm transition-colors disabled:opacity-40', (items ? 'items' : 'order') === key ? 'border-brand bg-brand-soft font-semibold' : 'border-border hover:border-border-strong')}>
                {label}
              </button>
            ))}
          </div>
          <input type="hidden" name="applies_to" value={items ? 'items' : 'order'} />
          {items ? (
            <>
              <Targets catalog={catalog} products={productIds} categories={categoryIds} onProducts={setProductIds} onCategories={setCategoryIds} error={e['rules.product_ids']} />
              {productIds.map((id) => <input key={id} type="hidden" name="product_ids" value={id} />)}
              {categoryIds.map((id) => <input key={id} type="hidden" name="category_ids" value={id} />)}
            </>
          ) : null}
        </fieldset>

        <div className="flex flex-wrap items-center gap-3">
          <Checkbox label="تا تاریخ مشخص" checked={hasEnd} onChange={(ev) => setHasEnd(ev.target.checked)} />
          {hasEnd ? (
            <div className="flex flex-wrap items-end gap-3 rounded-md border border-border px-3 py-2">
              <JalaliDateField label="تا روز" name="end_date" defaultValue={endDate} years={2} future onChange={setEndDate} />
              <ClockSelect label="ساعت" value={endTime} onChange={setEndTime} minuteStep={15} />
            </div>
          ) : null}
          <input type="hidden" name="ends_at" value={hasEnd ? localToIso(endDate, endTime, catalog.timezone) : ''} />
          {e.ends_at ? <p role="alert" className="w-full text-xs text-danger">{e.ends_at}</p> : null}
        </div>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-sm font-medium">روزهای فعال</legend>
          <div className="flex flex-wrap gap-2">
            {IRANIAN_WEEK.map((w) => (
              <label key={w} className="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="weekdays" value={w} defaultChecked={discount?.schedule?.weekdays?.includes(w)} className="accent-[var(--color-brand)]" />
                {WEEKDAY_LABELS[w]}
              </label>
            ))}
          </div>
          <p className="text-xs text-text-muted">هیچ روزی انتخاب نشود یعنی همه‌ی روزها.</p>
        </fieldset>

        <div className="flex flex-wrap items-center gap-3">
          <Checkbox label="فقط در ساعات مشخص" name="timed" checked={timed} onChange={(ev) => setTimed(ev.target.checked)} />
          {timed ? (
            <div className="flex items-center gap-2 rounded-md border border-border px-2 py-1 text-sm">
              <ClockSelect label="ساعت شروع" value={from} onChange={setFrom} minuteStep={15} />
              <span aria-hidden="true">تا</span>
              <ClockSelect label="ساعت پایان" value={to} onChange={setTo} minuteStep={15} />
              <input type="hidden" name="from" value={from} />
              <input type="hidden" name="to" value={to} />
            </div>
          ) : null}
        </div>

        <input type="hidden" name="rules_json" value={JSON.stringify(discount?.rules ?? [])} />
        {tiers.length > 0 && !onMenu ? (
          <div className="max-w-xs">
            <SelectField label="فقط برای سطح باشگاه" name="tier_id" defaultValue={discount?.rules?.find((r) => r.type === 'tier')?.target ?? ''}>
              <option value="">همه‌ی مشتریان</option>
              {tiers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </SelectField>
          </div>
        ) : null}

        <Checkbox label="فعال" name="is_active" defaultChecked={discount?.is_active ?? true} />

        <div className="flex gap-2">
          <Button type="submit" loading={pending}>ذخیره</Button>
          <Button variant="ghost" onClick={() => (discount ? setOpen(false) : setCreating(false))}>بستن</Button>
          {discount ? (
            <Button variant="ghost" loading={deleting} onClick={() => startDelete(() => deleteDiscount(discount.id))}>
              {discount.used_count > 0 ? 'غیرفعال کردن' : 'حذف'}
            </Button>
          ) : null}
        </div>
      </form>
    </Card>
  );
}
