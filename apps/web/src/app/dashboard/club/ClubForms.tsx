'use client';

import { useActionState, useState, useTransition } from 'react';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, SelectField, TextField } from '@cafe/ui';
import { formatMoney, formatNumber, toPersianDigits } from '@cafe/locale';
import { deleteCashbackRule, deleteTier, saveCashbackRule, saveProgram, saveTier } from '@/app/actions/club';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import type { CashbackRuleItem, Category, FormState, LoyaltyProgram } from '@/lib/types';

const num = (v: number | boolean | undefined) => (typeof v === 'number' ? v : 0);

export function ProgramForm({ settings }: { settings: LoyaltyProgram['settings'] }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveProgram, { ok: false });
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="تنظیمات باشگاه" description="امتیاز و کش‌بک فقط پس از «تحویل شد» شدن سفارش داده می‌شود و با بازگشت وجه، به همان نسبت برمی‌گردد." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <div className="flex flex-wrap gap-6">
          <Checkbox label="باشگاه مشتریان فعال است" name="loyalty.enabled" defaultChecked={Boolean(settings['loyalty.enabled'])} />
          <Checkbox label="پرداخت با کیف پول" name="wallet.payments_enabled" defaultChecked={Boolean(settings['wallet.payments_enabled'])} />
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField label="امتیاز به ازای هر ۱۰ هزار تومان خرید" name="loyalty.points_per_100k" defaultValue={String(num(settings['loyalty.points_per_100k']))} inputMode="numeric" ltr error={e['loyalty.points_per_100k']} />
          <MoneyField label="ارزش هر امتیاز (تومان)" name="loyalty.point_value" defaultValue={rialToTomanInput(num(settings['loyalty.point_value']))} error={e['loyalty.point_value']} />
          <TextField label="حداقل امتیاز برای تبدیل به کیف پول" name="loyalty.min_redeem_points" defaultValue={String(num(settings['loyalty.min_redeem_points']))} inputMode="numeric" ltr error={e['loyalty.min_redeem_points']} />
          <MoneyField label="هدیه‌ی تولد به کیف پول (تومان)" name="loyalty.birthday_wallet_gift" defaultValue={rialToTomanInput(num(settings['loyalty.birthday_wallet_gift']))} hint="۰ یعنی بدون هدیه" error={e['loyalty.birthday_wallet_gift']} />
          <TextField label="امتیاز هدیه‌ی تولد" name="loyalty.birthday_points" defaultValue={String(num(settings['loyalty.birthday_points']))} inputMode="numeric" ltr error={e['loyalty.birthday_points']} />
          <span />
          <MoneyField label="پاداش معرف (تومان)" name="loyalty.referral_referrer_reward" defaultValue={rialToTomanInput(num(settings['loyalty.referral_referrer_reward']))} hint="پس از اولین خرید دوست معرفی‌شده" error={e['loyalty.referral_referrer_reward']} />
          <MoneyField label="هدیه‌ی دوست معرفی‌شده (تومان)" name="loyalty.referral_referee_reward" defaultValue={rialToTomanInput(num(settings['loyalty.referral_referee_reward']))} error={e['loyalty.referral_referee_reward']} />
        </div>
        <p className="text-xs text-text-muted">هدیه‌ی تولد ساعت ۹ صبح روز تولد (تقویم شمسی) و سالی یک بار به کیف پول اضافه و پیامک می‌شود.</p>
        <div><Button type="submit" loading={pending}>ذخیره</Button></div>
      </form>
    </Card>
  );
}

export function TierEditor({ tier }: { tier?: LoyaltyProgram['tiers'][number] }) {
  const [open, setOpen] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveTier.bind(null, tier?.id ?? null), { ok: false });
  const [deleting, startDelete] = useTransition();
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const e = state.errors ?? {};

  if (!open) {
    return tier ? (
      <li className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
        <div className="flex items-center gap-3">
          <span className="size-3 rounded-full" style={{ backgroundColor: tier.color }} aria-hidden="true" />
          <div>
            <p className="font-medium">{tier.name}</p>
            <p className="text-xs text-text-muted">
              از {formatMoney(tier.min_spend)} خرید • ضریب امتیاز {toPersianDigits(String(tier.points_multiplier / 10000))}
              {tier.perks ? ` • ${tier.perks}` : ''}
            </p>
          </div>
        </div>
        <span className="flex items-center gap-2">
          <Badge>{formatNumber(tier.members)} عضو</Badge>
          <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>
        </span>
      </li>
    ) : (
      <li className="px-5 py-3"><Button size="sm" variant="secondary" onClick={() => setOpen(true)}>افزودن سطح</Button></li>
    );
  }

  return (
    <li className="px-5 py-3">
      <form action={action} className="flex flex-col gap-3">
        <FormStatus state={state} />
        {deleteError ? <Alert tone="danger">{deleteError}</Alert> : null}
        <div className="grid gap-3 sm:grid-cols-4">
          <TextField label="نام سطح" name="name" defaultValue={tier?.name} required placeholder="مثلاً طلایی" error={e.name} />
          <MoneyField label="از مجموع خرید (تومان)" name="min_spend" defaultValue={rialToTomanInput(tier?.min_spend ?? 0)} error={e.min_spend} />
          <TextField label="ضریب امتیاز" name="multiplier" defaultValue={tier ? String(tier.points_multiplier / 10000) : '1'} inputMode="decimal" ltr hint="۱٫۵ یعنی ۵۰٪ امتیاز بیشتر" error={e.points_multiplier} />
          <TextField label="رنگ" name="color" type="color" defaultValue={tier?.color ?? '#94A3B8'} className="p-1" error={e.color} />
          <div className="sm:col-span-4"><TextField label="مزایا" name="perks" defaultValue={tier?.perks ?? ''} placeholder="مثلاً قهوه‌ی رایگان روز تولد" error={e.perks} /></div>
        </div>
        <div className="flex gap-2">
          <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
          <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
          {tier ? (
            <Button size="sm" variant="ghost" loading={deleting} onClick={() => startDelete(async () => {
              const result = await deleteTier(tier.id);
              setDeleteError(result.ok ? null : result.message ?? null);
            })}>حذف</Button>
          ) : null}
        </div>
      </form>
    </li>
  );
}

function ruleSummary(rule: CashbackRuleItem, categories: Category[]): string {
  const reward = rule.kind === 'percent' ? `${toPersianDigits(String(rule.value / 100))}٪` : formatMoney(rule.value);
  const scope = rule.category_id ? `خرید از «${categories.find((c) => c.id === rule.category_id)?.name ?? 'دسته‌ی حذف‌شده'}»` : 'کل سفارش';
  const min = rule.min_spend > 0 ? ` از ${formatMoney(rule.min_spend)} به بالا` : '';
  const cap = rule.max_reward ? ` (حداکثر ${formatMoney(rule.max_reward)})` : '';

  return `${reward} کش‌بک برای ${scope}${min}${cap}`;
}

export function CashbackRuleEditor({ rule, categories }: { rule?: CashbackRuleItem; categories: Category[] }) {
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState<'fixed' | 'percent'>(rule?.kind ?? 'percent');
  const [state, action, pending] = useActionState<FormState, FormData>(saveCashbackRule.bind(null, rule?.id ?? null), { ok: false });
  const [deleting, startDelete] = useTransition();
  const e = state.errors ?? {};

  if (!open) {
    return rule ? (
      <li className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
        <div>
          <p className="font-medium">{rule.name} {!rule.is_active ? <Badge>غیرفعال</Badge> : null}</p>
          <p className="text-xs text-text-muted">{ruleSummary(rule, categories)}</p>
        </div>
        <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>
      </li>
    ) : (
      <li className="px-5 py-3"><Button size="sm" variant="secondary" onClick={() => setOpen(true)}>افزودن قانون کش‌بک</Button></li>
    );
  }

  return (
    <li className="px-5 py-3">
      <form action={action} className="flex flex-col gap-3">
        <FormStatus state={state} />
        <div className="grid gap-3 sm:grid-cols-3">
          <TextField label="نام" name="name" defaultValue={rule?.name} required error={e.name} />
          <SelectField label="روی خرید از" name="category_id" defaultValue={rule?.category_id ?? ''} error={e.category_id}>
            <option value="">کل سفارش</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </SelectField>
          <MoneyField label="حداقل خرید (تومان)" name="min_spend" defaultValue={rialToTomanInput(rule?.min_spend ?? 0)} error={e.min_spend} />
          <SelectField label="نوع" name="kind" value={kind} onChange={(ev) => setKind(ev.target.value as 'fixed' | 'percent')}>
            <option value="percent">درصدی</option>
            <option value="fixed">مبلغ ثابت</option>
          </SelectField>
          {kind === 'percent' ? (
            <TextField key="p" label="درصد" name="value" defaultValue={rule?.kind === 'percent' ? String(rule.value / 100) : ''} inputMode="decimal" ltr required error={e.value} />
          ) : (
            <MoneyField key="f" label="مبلغ (تومان)" name="value" defaultValue={rule?.kind === 'fixed' ? rialToTomanInput(rule.value) : ''} required error={e.value} />
          )}
          <MoneyField label="سقف کش‌بک (تومان)" name="max_reward" defaultValue={rialToTomanInput(rule?.max_reward)} hint="خالی = بدون سقف" error={e.max_reward} />
        </div>
        {rule ? <Checkbox label="فعال" name="is_active" defaultChecked={rule.is_active} /> : null}
        <div className="flex gap-2">
          <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
          <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
          {rule ? <Button size="sm" variant="ghost" loading={deleting} onClick={() => startDelete(async () => { await deleteCashbackRule(rule.id); })}>حذف</Button> : null}
        </div>
      </form>
    </li>
  );
}
