'use client';

import { useActionState } from 'react';
import { Alert, Button } from '@cafe/ui';
import { pairDevice } from '@/app/actions/kds';
import type { FormState } from '@/lib/types';

export function PairForm({ tenant }: { tenant: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(pairDevice, { ok: false });

  if (!tenant) {
    return <Alert tone="warning">این صفحه را با لینکی که پنل مدیریت برای دستگاه نشان می‌دهد باز کنید.</Alert>;
  }

  return (
    <form action={action} className="flex w-full flex-col gap-4">
      {state.message ? <Alert tone="danger">{state.message}</Alert> : null}
      <input type="hidden" name="tenant" value={tenant} />
      <label htmlFor="pair-code" className="sr-only">کد اتصال</label>
      <input
        id="pair-code"
        name="code"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={7}
        dir="ltr"
        autoFocus
        placeholder="••••••"
        aria-invalid={Boolean(state.errors?.code)}
        className="h-16 w-full rounded-lg border border-border-strong bg-surface text-center text-3xl font-bold tracking-[0.5em] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none"
      />
      {state.errors?.code ? <p className="text-sm text-danger">{state.errors.code}</p> : null}
      <Button type="submit" loading={pending} className="h-12 text-base">اتصال</Button>
    </form>
  );
}
