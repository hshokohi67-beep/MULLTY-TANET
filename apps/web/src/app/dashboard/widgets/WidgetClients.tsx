'use client';

import { useActionState, useState, useTransition } from 'react';
import { Send, Target, Trash2 } from 'lucide-react';
import { Button } from '@cafe/ui';
import { formatJalaliDateTime } from '@cafe/locale';
import { addShiftNote, deleteShiftNote, saveGoals } from '@/app/actions/dashboard-layout';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Inline editor for the daily/monthly sales goals (needs settings.update). */
export function GoalEditor({ daily, monthly }: { daily: number; monthly: number }) {
  const [open, setOpen] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveGoals, { ok: false });

  if (!open) {
    return <Button variant="ghost" size="sm" icon={<Target />} onClick={() => setOpen(true)}>{daily || monthly ? 'ویرایش هدف' : 'تعیین هدف'}</Button>;
  }

  return (
    <form action={action} className="mt-3 flex flex-col gap-3 border-t border-border pt-3">
      <FormStatus state={state} />
      <div className="grid gap-3 sm:grid-cols-2">
        <MoneyField label="هدف روزانه (تومان)" name="daily" defaultValue={rialToTomanInput(daily || null)} />
        <MoneyField label="هدف ماهانه (تومان)" name="monthly" defaultValue={rialToTomanInput(monthly || null)} />
      </div>
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
        <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
      </div>
    </form>
  );
}

export interface Note { id: string; body: string; author: string; author_id: string; created_at: string }

/** Shift handover notes: write one, see the last week's, delete your own. */
export function ShiftNotes({ notes, userId, canModerate }: { notes: Note[]; userId: string; canModerate: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, formData) => {
    const result = await addShiftNote(prev, formData);
    return result.ok ? { ok: false } : result; // clear the form after a successful post
  }, { ok: false });
  const [deleting, startDelete] = useTransition();

  return (
    <div className="flex flex-col gap-3">
      <form action={action} className="flex items-start gap-2">
        <label htmlFor="shift-note" className="sr-only">یادداشت برای شیفت بعد</label>
        <textarea id="shift-note" name="body" rows={2} maxLength={500} required placeholder="مثلاً: شیر بادام کم است؛ میز ۴ رزرو ساعت ۸…"
          className="min-h-11 flex-1 resize-none rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        <Button type="submit" size="md" loading={pending} aria-label="ثبت یادداشت" className="px-3"><Send className="size-4" /></Button>
      </form>
      {state.errors?.body ? <p className="text-xs text-danger">{state.errors.body}</p> : null}
      {notes.length === 0 ? (
        <p className="py-4 text-center text-sm text-text-muted">یادداشتی در هفته‌ی اخیر نیست.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {notes.map((n) => (
            <li key={n.id} className="rounded-lg bg-surface-muted px-3 py-2">
              <p className="text-sm">{n.body}</p>
              <p className="mt-1 flex items-center justify-between text-xs text-text-subtle">
                <span>{n.author} • {formatJalaliDateTime(n.created_at)}</span>
                {n.author_id === userId || canModerate ? (
                  <button type="button" disabled={deleting} onClick={() => startDelete(() => deleteShiftNote(n.id))} className="rounded p-1 hover:bg-danger-soft hover:text-danger" aria-label="حذف یادداشت">
                    <Trash2 className="size-3.5" />
                  </button>
                ) : null}
              </p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
