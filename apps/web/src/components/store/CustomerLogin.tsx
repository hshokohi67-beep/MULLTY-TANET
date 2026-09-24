'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { MessageSquareText, Smartphone } from 'lucide-react';
import { Alert, Button, Ltr } from '@cafe/ui';
import { formatNumber, toLatinDigits } from '@cafe/locale';
import { requestOtp, verifyOtp } from '@/app/actions/storefront';
import { useStore } from './StoreProvider';

/** Two calm steps: mobile number, then the SMS code (with a resend countdown). */
export function CustomerLogin({ next }: { next: string }) {
  const { tenant, store, reload } = useStore();
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [wait, setWait] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const codeRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (wait <= 0) return;
    const t = setTimeout(() => setWait((w) => w - 1), 1000);

    return () => clearTimeout(t);
  }, [wait]);

  const send = () => start(async () => {
    setError(null);
    const result = await requestOtp(tenant, phone);
    if (!result.ok) {
      setError(result.message);
      return;
    }
    setStep('code');
    setWait(result.data.resend_after);
    setTimeout(() => codeRef.current?.focus(), 50);
  });

  const verify = () => start(async () => {
    setError(null);
    const result = await verifyOtp(tenant, phone, code);
    if (!result.ok) {
      setError(result.message);
      return;
    }
    await reload();
    window.location.assign(next);
  });

  return (
    <div className="mx-auto flex max-w-sm flex-col gap-6 pt-10">
      <div className="text-center">
        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">
          {step === 'phone' ? <Smartphone className="size-7" aria-hidden="true" /> : <MessageSquareText className="size-7" aria-hidden="true" />}
        </span>
        <h1 className="mt-4 text-2xl font-bold">{step === 'phone' ? `ورود به ${store.name}` : 'کد تأیید'}</h1>
        <p className="mt-1 text-sm text-text-muted">
          {step === 'phone' ? 'با شماره موبایل وارد شوید؛ اگر بار اول است، حسابتان خودکار ساخته می‌شود.' : <>کد پیامک‌شده به <Ltr>{phone}</Ltr> را وارد کنید.</>}
        </p>
      </div>

      {error ? <Alert tone="danger">{error}</Alert> : null}

      {step === 'phone' ? (
        <form className="flex flex-col gap-3" onSubmit={(e) => { e.preventDefault(); send(); }}>
          <label htmlFor="phone" className="text-sm font-medium">شماره موبایل</label>
          <input id="phone" type="tel" inputMode="tel" autoComplete="tel" dir="ltr" required value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="09xx xxx xxxx"
            className="h-12 rounded-xl border border-border-strong bg-surface px-4 text-center text-lg tracking-wider focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
          <Button type="submit" size="lg" loading={pending}>دریافت کد</Button>
        </form>
      ) : (
        <form className="flex flex-col gap-3" onSubmit={(e) => { e.preventDefault(); verify(); }}>
          <label htmlFor="otp" className="sr-only">کد تأیید</label>
          <input id="otp" ref={codeRef} inputMode="numeric" autoComplete="one-time-code" dir="ltr" required maxLength={8} value={code}
            onChange={(e) => setCode(toLatinDigits(e.target.value).replace(/\D/g, ''))}
            className="h-14 rounded-xl border border-border-strong bg-surface px-4 text-center text-2xl tracking-[0.5em] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
          <Button type="submit" size="lg" loading={pending}>ورود</Button>
          <div className="flex items-center justify-between text-sm">
            <button type="button" onClick={() => { setStep('phone'); setCode(''); }} className="text-text-muted hover:text-text">تغییر شماره</button>
            {wait > 0 ? <span className="text-text-muted">ارسال دوباره تا {formatNumber(wait)} ثانیه</span> : (
              <button type="button" onClick={send} disabled={pending} className="font-medium text-brand hover:underline">ارسال دوباره‌ی کد</button>
            )}
          </div>
        </form>
      )}
    </div>
  );
}
