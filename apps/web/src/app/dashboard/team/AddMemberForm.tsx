'use client';

import { useActionState } from 'react';
import { Button, Card, CardHeader, TextField } from '@cafe/ui';
import { addTeamMember } from '@/app/actions/dashboard';
import { FormStatus } from '@/components/FormStatus';
import type { FormState, Role } from '@/lib/types';

export function AddMemberForm({ roles }: { roles: Role[] }) {
  const [state, action, pending] = useActionState<FormState, FormData>(addTeamMember, { ok: false });
  const e = state.errors ?? {};
  const roleError = e.role_ids ?? e['role_ids.0'];

  return (
    <Card>
      <CardHeader title="افزودن همکار" description="همکار با همین شماره موبایل و رمزی که تعیین می‌کنید وارد پنل می‌شود." />
      <form action={action} className="flex flex-col gap-4 p-5" key={state.ok ? 'reset' : 'form'}>
        <FormStatus state={state} />
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField label="نام" name="name" required error={e.name} />
          <TextField label="شماره موبایل" name="phone" inputMode="tel" ltr required error={e.phone} placeholder="۰۹۱۲۱۲۳۴۵۶۷" />
          <TextField label="رمز عبور اولیه" name="password" type="password" autoComplete="new-password" ltr error={e.password} hint="دست‌کم ۸ نویسه" />
        </div>
        <fieldset aria-describedby={roleError ? 'role-error' : undefined}>
          <legend className="mb-2 text-sm font-medium">نقش</legend>
          <div className="flex flex-wrap gap-3">
            {roles.map((role) => (
              <label key={role.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="role_ids" value={role.id} className="accent-[var(--color-brand)]" />
                {role.name}
              </label>
            ))}
          </div>
          {roleError ? <p id="role-error" role="alert" className="mt-1.5 text-xs text-danger">{roleError}</p> : null}
        </fieldset>
        <div><Button type="submit" loading={pending}>افزودن به تیم</Button></div>
      </form>
    </Card>
  );
}
