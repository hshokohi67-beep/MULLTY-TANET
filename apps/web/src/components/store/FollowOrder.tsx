'use client';

import { useEffect, useState, useTransition } from 'react';
import { BellRing, Check } from 'lucide-react';
import { cx } from '@cafe/ui';
import { followOrder, pushKey } from '@/app/actions/push';
import { pushSupport, subscribeBrowser, type PushSupport } from '@/lib/push-client';

/**
 * «وقتی آماده شد خبرم کن»: this browser gets a notification when the order is ready, on its way,
 * or cancelled, even with the page closed. Hidden when the platform or browser can't do it;
 * on iPhone it explains that the site must be added to the home screen first.
 */
export function FollowOrder({ tenant, orderId }: { tenant: string; orderId: string }) {
  const [support, setSupport] = useState<PushSupport | 'loading'>('loading');
  const [key, setKey] = useState<string | null>(null);
  const [state, setState] = useState<'idle' | 'on' | 'error'>('idle');
  const [message, setMessage] = useState<string | null>(null);
  const [pending, start] = useTransition();

  useEffect(() => {
    let live = true;
    void pushKey().then((k) => {
      if (!live) return;
      setKey(k);
      setSupport(pushSupport());
    });

    return () => { live = false; };
  }, []);

  if (support === 'loading' || !key || support === 'unsupported') return null;

  if (support === 'ios-needs-install') {
    return <p className="rounded-2xl bg-surface-muted px-4 py-3 text-center text-sm leading-7 text-text-muted">برای اعلان روی آیفون، اول با «Share ← Add to Home Screen» این صفحه را به صفحه‌ی اصلی اضافه کنید.</p>;
  }

  const follow = () => start(async () => {
    setMessage(null);
    try {
      const sub = await subscribeBrowser(key);
      if (!sub) {
        setState('error');
        setMessage('اجازه‌ی اعلان داده نشد؛ از تنظیمات مرورگر می‌توانید روشنش کنید.');
        return;
      }
      const token = new URLSearchParams(window.location.hash.slice(1)).get('t') ?? '';
      const result = await followOrder(tenant, orderId, token, sub, window.location.pathname + window.location.hash);
      setState(result.ok ? 'on' : 'error');
      if (!result.ok) setMessage(result.message);
    } catch {
      setState('error');
      setMessage('این مرورگر اعلان را نپذیرفت؛ مرورگر دیگری را امتحان کنید.');
    }
  });

  return (
    <div className="flex flex-col items-center gap-2">
      <button type="button" onClick={follow} disabled={pending || state === 'on' || support === 'denied'}
        className={cx('inline-flex h-12 items-center gap-2 rounded-2xl px-5 font-semibold transition-colors disabled:cursor-default',
          state === 'on' ? 'bg-success-soft text-success' : 'bg-brand text-on-brand hover:bg-brand-strong disabled:opacity-70')}>
        {state === 'on' ? <Check className="size-5" aria-hidden="true" /> : <BellRing className="size-5" aria-hidden="true" />}
        {state === 'on' ? 'خبرتان می‌کنیم' : 'وقتی آماده شد خبرم کن'}
      </button>
      {support === 'denied' ? <p className="text-center text-xs text-text-muted">اعلان این سایت در مرورگرتان خاموش است.</p> : null}
      {message ? <p role="status" className="text-center text-xs text-text-muted">{message}</p> : null}
    </div>
  );
}
