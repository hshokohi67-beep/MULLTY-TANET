'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { Check, Eye, EyeOff, Loader2, X } from 'lucide-react';
import { Alert, Button, TextField, cx } from '@cafe/ui';
import { formatNumber, toLatinDigits } from '@cafe/locale';
import { checkSlug, sendSignupCode, signUp, type SlugCheck } from '@/app/actions/signup';

type Errors = Record<string, string>;

/**
 * Two steps: the café and its owner, then the code sent to the owner's mobile. The address is
 * suggested from the café's name (editable) and checked as you type.
 */
export function SignupForm({ base }: { base: string }) {
  const [step, setStep] = useState<'details' | 'code'>('details');
  const [form, setForm] = useState({ cafe_name: '', slug: '', owner_name: '', phone: '', password: '', code: '', website: '' });
  const [slugEdited, setSlugEdited] = useState(false);
  const [check, setCheck] = useState<SlugCheck | null>(null);
  const [checking, setChecking] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState<Errors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [cooldown, setCooldown] = useState(0);
  const [pending, start] = useTransition();
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));

  // Suggest / check the address a moment after typing stops.
  useEffect(() => {
    clearTimeout(timer.current);
    if (!form.cafe_name.trim() && !form.slug) return;
    timer.current = setTimeout(async () => {
      setChecking(true);
      const result = await checkSlug(form.cafe_name, slugEdited ? form.slug : '');
      setChecking(false);
      if (!result) return;
      setCheck(result);
      if (!slugEdited) setForm((f) => ({ ...f, slug: result.suggestion }));
    }, 450);

    return () => clearTimeout(timer.current);
  }, [form.cafe_name, form.slug, slugEdited]);

  useEffect(() => {
    if (cooldown <= 0) return;
    const t = setTimeout(() => setCooldown((c) => c - 1), 1000);

    return () => clearTimeout(t);
  }, [cooldown]);

  const slugOk = slugEdited ? Boolean(check?.available && check.slug === form.slug) : Boolean(form.slug);

  const sendCode = () => start(async () => {
    setErrors({});
    setMessage(null);
    const local: Errors = {};
    if (form.cafe_name.trim().length < 2) local.cafe_name = 'نام کافه را بنویسید.';
    if (form.owner_name.trim().length < 2) local.owner_name = 'نام خودتان را بنویسید.';
    if (!/^(\+98|0)?9\d{9}$/.test(toLatinDigits(form.phone).replace(/\s/g, ''))) local.phone = 'شماره موبایل درست نیست (مثل ۰۹۱۲۳۴۵۶۷۸۹).';
    if (form.password.length < 8 || !/\d/.test(form.password) || !/[a-zA-Z]/.test(form.password)) local.password = 'حداقل ۸ نویسه، با حرف انگلیسی و عدد.';
    if (!slugOk) local.slug = 'این آدرس آزاد نیست؛ آدرس دیگری بنویسید.';
    if (Object.keys(local).length) { setErrors(local); return; }

    const result = await sendSignupCode(form.phone, form.website);
    if (!result.ok) { setErrors(result.errors ?? {}); setMessage(result.message); return; }
    setCooldown(result.resendAfter);
    setStep('code');
  });

  const create = () => start(async () => {
    setErrors({});
    setMessage(null);
    const result = await signUp(form);
    // On success the action redirects to the panel; we only get here on an error.
    setErrors(result.errors ?? {});
    setMessage(result.message);
    if (result.errors && ['cafe_name', 'slug', 'owner_name', 'password', 'phone'].some((k) => k in (result.errors ?? {}))) setStep('details');
  });

  return (
    <div className="flex flex-col gap-4">
      {message ? <Alert tone="danger">{message}</Alert> : null}

      {step === 'details' ? (
        <form onSubmit={(e) => { e.preventDefault(); sendCode(); }} className="flex flex-col gap-4" noValidate>
          <TextField label="نام کافه یا رستوران" required value={form.cafe_name} maxLength={80} placeholder="مثلاً کافه نارنج" error={errors.cafe_name}
            onChange={(e) => set('cafe_name', e.target.value)} autoComplete="organization" />

          <div className="flex flex-col gap-1.5">
            <label htmlFor="slug" className="text-sm font-medium">آدرس اختصاصی</label>
            <div dir="ltr" className={cx('flex h-11 items-center overflow-hidden rounded-xl border bg-surface text-sm focus-within:shadow-[var(--focus-ring)]', errors.slug ? 'border-danger' : 'border-border-strong')}>
              <input id="slug" value={form.slug} maxLength={30} spellCheck={false} autoCapitalize="none"
                onChange={(e) => { setSlugEdited(true); set('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '')); }}
                className="h-full min-w-0 flex-1 bg-transparent px-3 outline-none" />
              <span className="shrink-0 bg-surface-muted px-3 py-3 text-text-muted">.{check?.base ?? base}</span>
            </div>
            <p className="flex items-center gap-1.5 text-xs text-text-muted">
              {checking ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : slugOk ? <Check className="size-3.5 text-success" aria-hidden="true" /> : form.slug ? <X className="size-3.5 text-danger" aria-hidden="true" /> : null}
              {errors.slug ?? (slugOk ? 'آزاد است؛ منو و سایت کافه روی این آدرس باز می‌شود.' : form.slug ? 'گرفته شده یا مجاز نیست.' : 'از روی نام کافه پیشنهاد می‌شود.')}
            </p>
          </div>

          <TextField label="نام و نام خانوادگی شما" required value={form.owner_name} maxLength={80} error={errors.owner_name} onChange={(e) => set('owner_name', e.target.value)} autoComplete="name" />
          <TextField label="شماره موبایل" required value={form.phone} inputMode="tel" ltr placeholder="09123456789" error={errors.phone} onChange={(e) => set('phone', e.target.value)} autoComplete="tel" />
          <div className="relative">
            <TextField label="رمز عبور پنل" required type={showPassword ? 'text' : 'password'} value={form.password} ltr error={errors.password}
              hint="حداقل ۸ نویسه، با حرف انگلیسی و عدد" onChange={(e) => set('password', e.target.value)} autoComplete="new-password" />
            <button type="button" onClick={() => setShowPassword((s) => !s)} aria-label={showPassword ? 'پنهان کردن رمز' : 'نمایش رمز'}
              className="absolute end-2 top-8 flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted">
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
          {/* Honeypot: invisible to people, filled by bots. */}
          <input type="text" name="website" value={form.website} onChange={(e) => set('website', e.target.value)} tabIndex={-1} autoComplete="off" aria-hidden="true" className="absolute -start-[9999px] size-px opacity-0" />

          <Button type="submit" size="lg" loading={pending}>ارسال کد تأیید</Button>
          <p className="text-center text-xs leading-6 text-text-subtle">۱۴ روز رایگان با همه‌ی امکانات پلن حرفه‌ای؛ بدون کارت بانکی. بعد خودتان پلن را انتخاب می‌کنید.</p>
        </form>
      ) : (
        <form onSubmit={(e) => { e.preventDefault(); create(); }} className="flex flex-col gap-4" noValidate>
          <p className="text-sm leading-7 text-text-muted">کد تأیید به <span dir="ltr" className="font-semibold text-text">{form.phone}</span> پیامک شد.</p>
          <TextField label="کد تأیید" required value={form.code} inputMode="numeric" ltr maxLength={8} autoComplete="one-time-code" error={errors.code}
            onChange={(e) => set('code', toLatinDigits(e.target.value).replace(/\D/g, ''))} className="text-center text-lg tracking-[0.5em]" />
          <Button type="submit" size="lg" loading={pending} disabled={form.code.length < 4}>ساختن کافه</Button>
          <div className="flex items-center justify-between text-sm">
            <button type="button" onClick={() => setStep('details')} className="text-text-muted hover:text-text">ویرایش اطلاعات</button>
            <button type="button" disabled={cooldown > 0 || pending} onClick={sendCode} className="font-semibold text-brand disabled:font-normal disabled:text-text-subtle">
              {cooldown > 0 ? `ارسال دوباره تا ${formatNumber(cooldown)} ثانیه` : 'ارسال دوباره‌ی کد'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
