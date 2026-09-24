'use client';

import { useActionState } from 'react';
import { Alert, Button, Card, TextField } from '@cafe/ui';
import { login } from '@/app/actions/auth';
import type { FormState } from '@/lib/types';

export function LoginForm({ next, expired }: { next: string; expired: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(login, { ok: false });

  return (
    <Card className="p-6 shadow-[var(--shadow-md)]">
      <form action={action} className="flex flex-col gap-4" noValidate>
        {expired && !state.message ? <Alert tone="warning">نشست شما به پایان رسیده است. لطفاً دوباره وارد شوید.</Alert> : null}
        {state.message ? <Alert tone="danger">{state.message}</Alert> : null}

        <input type="hidden" name="next" value={next} />
        <TextField
          label="موبایل یا ایمیل"
          name="identifier"
          autoComplete="username"
          inputMode="email"
          placeholder="۰۹۱۲۱۲۳۴۵۶۷"
          ltr
          required
          error={state.errors?.identifier}
        />
        <TextField
          label="رمز عبور"
          name="password"
          type="password"
          autoComplete="current-password"
          ltr
          required
          error={state.errors?.password}
        />
        <Button type="submit" size="lg" loading={pending} className="mt-1 w-full">ورود</Button>
      </form>
    </Card>
  );
}
