'use client';

import { useEffect, useState, useTransition } from 'react';
import { BellRing } from 'lucide-react';
import { cx } from '@cafe/ui';
import { pushKey, staffPushStatus, staffSubscribe, staffUnsubscribe } from '@/app/actions/push';
import { currentSubscription, pushSupport, subscribeBrowser, type PushSupport } from '@/lib/push-client';

/**
 * «اعلان سفارش تازه روی این دستگاه»: this browser shows a notification for every new order, even
 * when the panel tab is in the background. Hidden when push is off or impossible here.
 */
export function DevicePushToggle() {
  const [support, setSupport] = useState<PushSupport | 'loading'>('loading');
  const [key, setKey] = useState<string | null>(null);
  const [on, setOn] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const [pending, start] = useTransition();

  useEffect(() => {
    let live = true;
    void (async () => {
      const k = await pushKey();
      const s = pushSupport();
      const sub = s === 'ok' && k ? await currentSubscription().catch(() => null) : null;
      const subscribed = sub ? await staffPushStatus(sub.endpoint) : false;
      if (!live) return;
      setKey(k);
      setSupport(s);
      setOn(subscribed);
    })();

    return () => { live = false; };
  }, []);

  if (support === 'loading' || !key || support === 'unsupported') return null;

  const toggle = () => start(async () => {
    setNote(null);
    try {
      if (on) {
        const sub = await currentSubscription();
        if (sub) await staffUnsubscribe(sub.endpoint);
        setOn(false);
        return;
      }
      const sub = await subscribeBrowser(key);
      if (!sub) { setNote('اجازه‌ی اعلان داده نشد.'); return; }
      const result = await staffSubscribe(sub);
      setOn(result.ok);
      if (!result.ok) setNote(result.message);
    } catch {
      setNote('این مرورگر اعلان را نپذیرفت.');
    }
  });

  return (
    <div className="border-t border-border px-4 py-3">
      {support === 'ios-needs-install' ? (
        <p className="text-xs leading-6 text-text-muted">برای اعلان روی آیفون، پنل را به صفحه‌ی اصلی اضافه کنید (Share ← Add to Home Screen).</p>
      ) : (
        <label className={cx('flex cursor-pointer items-center gap-3', (pending || support === 'denied') && 'cursor-default opacity-70')}>
          <BellRing className="size-4 shrink-0 text-text-subtle" aria-hidden="true" />
          <span className="flex-1 text-sm">اعلان سفارش تازه روی این دستگاه</span>
          <input type="checkbox" className="peer sr-only" checked={on} disabled={pending || support === 'denied'} onChange={toggle} />
          <span aria-hidden="true" className="relative h-5 w-9 shrink-0 rounded-full bg-border-strong transition-colors after:absolute after:start-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-surface after:shadow after:transition-all peer-checked:bg-success peer-checked:after:start-[1.125rem] peer-focus-visible:outline-2 peer-focus-visible:outline-brand" />
        </label>
      )}
      {support === 'denied' ? <p className="mt-1 text-xs text-text-muted">اعلان این سایت در مرورگر خاموش است.</p> : null}
      {note ? <p role="status" className="mt-1 text-xs text-text-muted">{note}</p> : null}
    </div>
  );
}
