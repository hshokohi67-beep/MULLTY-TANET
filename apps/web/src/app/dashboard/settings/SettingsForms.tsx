'use client';

import { useActionState } from 'react';
import { Alert, Button, Card, CardHeader, Checkbox, SelectField, TextAreaField, TextField } from '@cafe/ui';
import { updateBranding, updatePaymentSettings, updatePreorderSettings, updateReportSettings, updateSettings, updateTenantProfile } from '@/app/actions/dashboard';
import { FormStatus } from '@/components/FormStatus';
import type { Branding, FormState, SettingItem, SettingsMeta, Tenant } from '@/lib/types';

const initial: FormState = { ok: false };

export function TenantProfileForm({ tenant, readOnly }: { tenant: Tenant; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updateTenantProfile, initial);

  return (
    <Card>
      <CardHeader title="اطلاعات کسب‌وکار" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <TextField label="نام کسب‌وکار" name="name" defaultValue={tenant.name} required error={state.errors?.name} />
          <SelectField label="واحد نمایش قیمت‌ها" name="display_currency_unit" defaultValue={tenant.display_currency_unit} error={state.errors?.display_currency_unit} hint="مبالغ همیشه به ریال ذخیره می‌شوند؛ این فقط نحوه‌ی نمایش است.">
            <option value="toman">تومان</option>
            <option value="rial">ریال</option>
          </SelectField>
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

export function BrandingForm({ branding, readOnly }: { branding: Branding; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updateBranding, initial);
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="برند و سئو" description="این اطلاعات در منوی آنلاین و نتایج جستجوی گوگل دیده می‌شود." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <div className="flex items-center gap-4 sm:col-span-2">
            {branding.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- tenant-uploaded media from object storage
              <img src={branding.logo_url} alt="لوگوی فعلی" className="size-16 rounded-md border border-border object-contain" />
            ) : (
              <div className="flex size-16 items-center justify-center rounded-md border border-dashed border-border-strong text-xs text-text-subtle">بدون لوگو</div>
            )}
            <div className="flex-1">
              <TextField label="لوگو" name="logo" type="file" accept="image/png,image/jpeg,image/webp" error={e.logo} hint="PNG، JPG یا WebP، حداکثر ۱ مگابایت، مربعی و دست‌کم ۶۴ پیکسل" />
            </div>
          </div>
          <div className="flex flex-col gap-3 sm:col-span-2">
            <div className="relative h-32 overflow-hidden rounded-xl border border-border">
              {branding.cover_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- tenant-uploaded media from object storage
                <img src={branding.cover_url} alt="کاور فعلی" className="size-full object-cover" />
              ) : (
                <div className="flex size-full items-center justify-center bg-surface-muted text-sm text-text-subtle">بدون کاور؛ رنگ برند با طرح ملایم نمایش داده می‌شود</div>
              )}
            </div>
            <TextField label="تصویر کاور منوی آنلاین" name="cover" type="file" accept="image/png,image/jpeg,image/webp" error={e.cover}
              hint="عکس افقی از فضای کافه یا محصولات، دست‌کم ۸۰۰×۳۰۰ پیکسل، تا ۸ مگابایت. خودکار فشرده و بهینه می‌شود." />
            {branding.cover_url ? <Checkbox name="remove_cover" label="حذف کاور فعلی" /> : null}
          </div>
          <TextField label="رنگ اصلی" name="primary_color" type="color" defaultValue={branding.primary_color} error={e.primary_color} className="p-1" />
          <SelectField label="پوسته" name="theme" defaultValue={branding.theme} error={e.theme}>
            <option value="light">روشن</option>
            <option value="dark">تیره</option>
          </SelectField>
          <TextField label="عنوان سئو" name="seo_title" defaultValue={branding.seo_title ?? ''} maxLength={70} error={e.seo_title} hint="حداکثر ۷۰ نویسه" />
          <div className="sm:col-span-2">
            <TextAreaField label="توضیحات سئو" name="seo_description" defaultValue={branding.seo_description ?? ''} maxLength={170} error={e.seo_description} hint="حداکثر ۱۷۰ نویسه؛ خلاصه‌ای جذاب از کافه برای نتایج جستجو" />
          </div>
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

export function GeneralSettingsForm({ settings, readOnly }: { settings: SettingItem[]; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updateSettings, initial);
  const byKey = Object.fromEntries(settings.map((s) => [s.key, s]));
  const apiKey = byKey['integrations.sms.kavenegar_api_key'];
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="تنظیمات عمومی" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <TextField label="تلفن تماس" name="contact.phone" defaultValue={String(byKey['contact.phone']?.value ?? '')} inputMode="tel" ltr error={e['contact.phone']} />
          <TextField label="اینستاگرام" name="contact.instagram" defaultValue={String(byKey['contact.instagram']?.value ?? '')} ltr error={e['contact.instagram']} hint="فقط نام کاربری، بدون @" />
          {apiKey ? (
            <div className="sm:col-span-2">
              <TextField
                label="کلید API کاوه‌نگار"
                name="integrations.sms.kavenegar_api_key"
                type="password"
                autoComplete="off"
                ltr
                placeholder={apiKey.is_set ? apiKey.masked ?? '' : ''}
                error={e['integrations.sms.kavenegar_api_key']}
                hint={apiKey.is_set ? 'کلید ثبت شده است. برای تغییر، کلید جدید را وارد کنید؛ خالی بگذارید تا تغییری نکند.' : 'کلید به‌صورت رمزنگاری‌شده ذخیره می‌شود و دیگر نمایش داده نمی‌شود.'}
              />
            </div>
          ) : null}
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

export function PaymentSettingsForm({ settings, meta, readOnly }: { settings: SettingItem[]; meta: SettingsMeta; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updatePaymentSettings, initial);
  const byKey = Object.fromEntries(settings.map((s) => [s.key, s]));
  const merchant = byKey['payments.zarinpal.merchant_id'];
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader
        title="پرداخت اینترنتی (زرین‌پال)"
        description="با فعال بودن این گزینه، مشتری هنگام ثبت سفارش می‌تواند آنلاین پرداخت کند و پول مستقیم به حساب درگاه شما واریز می‌شود."
      />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        {meta.payments.test_mode ? (
          <Alert tone="warning">درگاه در حالت آزمایشی است؛ پرداخت‌ها واقعی نیستند و پولی جابه‌جا نمی‌شود.</Alert>
        ) : null}
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <Checkbox
            label="پذیرش پرداخت اینترنتی"
            name="payments.online.enabled"
            defaultChecked={Boolean(byKey['payments.online.enabled']?.value)}
            hint="پرداخت در صندوق همیشه در دسترس است."
            className="sm:col-span-2"
          />
          {merchant ? (
            <div className="sm:col-span-2">
              <TextField
                label="مرچنت کد زرین‌پال"
                name="payments.zarinpal.merchant_id"
                type="password"
                autoComplete="off"
                ltr
                placeholder={merchant.is_set ? merchant.masked ?? '' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'}
                error={e['payments.zarinpal.merchant_id']}
                hint={merchant.is_set ? 'مرچنت کد ثبت شده است. برای تغییر، کد جدید را وارد کنید؛ خالی بگذارید تا تغییری نکند.' : 'کد ۳۶ نویسه‌ای که در پنل زرین‌پال می‌بینید. رمزنگاری‌شده ذخیره می‌شود.'}
              />
            </div>
          ) : null}
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

export function ReportSettingsForm({ settings, readOnly }: { settings: SettingItem[]; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updateReportSettings, initial);
  const byKey = Object.fromEntries(settings.map((s) => [s.key, s]));
  const hour = Number(byKey['reports.daily_sms_hour']?.value ?? 23);

  return (
    <Card>
      <CardHeader title="گزارش پایان روز" description="خلاصه‌ی فروش هر روز با پیامک برای مالک کافه فرستاده می‌شود؛ حتی وقتی در کافه نیستید در جریان باشید." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <Checkbox label="ارسال پیامک گزارش روزانه" name="reports.daily_sms" defaultChecked={Boolean(byKey['reports.daily_sms']?.value)} className="sm:col-span-2" />
          <SelectField label="ساعت ارسال" name="reports.daily_sms_hour" defaultValue={String(hour)} hint="معمولاً ساعت بسته شدن کافه">
            {Array.from({ length: 24 }, (_, h) => <option key={h} value={h}>{new Intl.NumberFormat('fa-IR').format(h)}:۰۰</option>)}
          </SelectField>
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

const fa = (n: number) => new Intl.NumberFormat('fa-IR').format(n);

/** Pre-order rules: how soon, how far ahead, slot length, capacity and when it reaches the kitchen. */
export function PreorderSettingsForm({ settings, readOnly }: { settings: SettingItem[]; readOnly: boolean }) {
  const [state, action, pending] = useActionState(updatePreorderSettings, initial);
  const byKey = Object.fromEntries(settings.map((s) => [s.key, s]));
  const num = (key: string, fallback: number) => Number(byKey[key]?.value ?? fallback);
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="پیش‌سفارش" description="مشتری روز و ساعت دریافت را از میان بازه‌های باز کافه انتخاب می‌کند؛ سفارش کمی قبل از آن زمان به آشپزخانه می‌رود." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <Checkbox label="پذیرش پیش‌سفارش وقتی کافه بسته است" name="orders.allow_preorder_when_closed" className="sm:col-span-2"
            defaultChecked={Boolean(byKey['orders.allow_preorder_when_closed']?.value)} hint="اگر خاموش باشد، در ساعات تعطیلی سفارشی ثبت نمی‌شود." />
          <SelectField label="زودترین زمان دریافت" name="preorder.lead_minutes" defaultValue={String(num('preorder.lead_minutes', 30))} error={e['preorder.lead_minutes']} hint="از لحظه‌ی ثبت سفارش">
            {[0, 15, 30, 45, 60, 90, 120, 180].map((m) => <option key={m} value={m}>{m === 0 ? 'بدون فاصله' : `${fa(m)} دقیقه بعد`}</option>)}
          </SelectField>
          <SelectField label="تا چند روز جلوتر" name="preorder.max_days" defaultValue={String(num('preorder.max_days', 3))} error={e['preorder.max_days']}>
            {[0, 1, 2, 3, 5, 7, 14].map((d) => <option key={d} value={d}>{d === 0 ? 'فقط امروز' : `${fa(d)} روز`}</option>)}
          </SelectField>
          <SelectField label="فاصله‌ی بازه‌های زمانی" name="preorder.slot_minutes" defaultValue={String(num('preorder.slot_minutes', 15))} error={e['preorder.slot_minutes']}>
            {[10, 15, 20, 30, 60].map((m) => <option key={m} value={m}>{`هر ${fa(m)} دقیقه`}</option>)}
          </SelectField>
          <TextField label="ظرفیت هر بازه" name="preorder.slot_capacity" inputMode="numeric" ltr defaultValue={String(num('preorder.slot_capacity', 0))} error={e['preorder.slot_capacity']}
            hint="حداکثر سفارش در هر بازه؛ ۰ یعنی نامحدود. بازه‌ی پر برای مشتری غیرفعال می‌شود." />
          <SelectField label="ارسال به آشپزخانه" name="preorder.release_minutes" defaultValue={String(num('preorder.release_minutes', 20))} error={e['preorder.release_minutes']} hint="تا آن زمان در ستون «پیش‌سفارش‌ها»ی صندوق می‌ماند.">
            {[0, 10, 15, 20, 30, 45, 60, 90].map((m) => <option key={m} value={m}>{m === 0 ? 'سر همان ساعت' : `${fa(m)} دقیقه قبل`}</option>)}
          </SelectField>
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}
