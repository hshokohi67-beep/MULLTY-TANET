'use client';

import { useActionState, useEffect, useState } from 'react';
import { Clock, LogIn, LogOut, Pencil, Plus, Trash2, UserCheck } from 'lucide-react';
import { Badge, Button, Checkbox, ClockSelect, cx, Dialog, EmptyState, SelectField, TextField } from '@cafe/ui';
import { addDays, formatTime, toPersianDigits } from '@cafe/locale';
import { deleteAttendance, saveAttendance } from '@/app/actions/operations';
import { FormStatus } from '@/components/FormStatus';
import { formatHours, formatMinutes, isoToLocal, localToIso, type AttendanceRecord, type Employee } from '@/lib/operations-types';
import type { FormState } from '@/lib/types';

/** Minutes since an instant, refreshed every minute (null until mounted, so server and client agree). */
function useElapsed(): (iso: string) => number | null {
  const [now, setNow] = useState<number | null>(null);
  useEffect(() => {
    const tick = () => setNow(Date.now());
    const first = setTimeout(tick, 0);
    const timer = setInterval(tick, 60_000);

    return () => { clearTimeout(first); clearInterval(timer); };
  }, []);

  return (iso) => (now === null ? null : Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60_000)));
}

/** Who is in right now, the day's records (late, edited), and manual corrections by a manager. */
export function AttendanceBoard({ records, employees, day, isToday, timezone }: { records: AttendanceRecord[]; employees: Employee[]; day: string; isToday: boolean; timezone: string }) {
  const [editing, setEditing] = useState<AttendanceRecord | 'new' | null>(null);
  const elapsed = useElapsed();
  const open = records.filter((r) => r.clock_out_at === null);
  // The API also returns still-open records from earlier days; list them with today's.
  const dayRecords = records.filter((r) => r.clock_out_at !== null || isToday || isoToLocal(r.clock_in_at, timezone).date === day);
  const time = (iso: string) => formatTime(iso, timezone);

  return (
    <div className="flex flex-col gap-4">
      {isToday && open.length ? (
        <section aria-label="الان سر کار" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {open.map((r) => {
            const mins = elapsed(r.clock_in_at);
            const stale = mins !== null && mins > 16 * 60;

            return (
              <div key={r.id} className={cx('flex items-center gap-3 rounded-2xl border bg-surface p-3.5 shadow-[var(--shadow-sm)]', stale ? 'border-warning' : 'border-border')}>
                <span className="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-success-soft text-success">
                  <UserCheck className="size-5" aria-hidden="true" />
                  <span className="absolute -end-0.5 -top-0.5 size-3 animate-pulse rounded-full border-2 border-surface bg-success" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-semibold">{r.employee.name}</p>
                  <p className="text-xs text-text-muted">
                    از {time(r.clock_in_at)}{mins !== null ? <> • <span className="tabular">{formatHours(mins)}</span></> : null}
                    {r.late_minutes > 0 ? <span className="text-warning"> • {toPersianDigits(r.late_minutes)} دقیقه تأخیر</span> : null}
                  </p>
                  {stale ? <p className="text-xs font-medium text-warning">احتمالاً خروج نزده؛ اصلاح کنید.</p> : null}
                </div>
                <Button size="sm" variant="secondary" icon={<LogOut />} onClick={() => setEditing(r)}>خروج</Button>
              </div>
            );
          })}
        </section>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)]">
        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
          <h2 className="text-sm font-semibold">ورود و خروج‌ها</h2>
          <Button size="sm" variant="secondary" icon={<Plus />} onClick={() => setEditing('new')} disabled={employees.length === 0}>ثبت دستی</Button>
        </div>
        {dayRecords.length === 0 ? (
          <EmptyState icon={<Clock />} title="در این روز ورودی ثبت نشده"
            description="کارکنانی که حساب کاربری دارند با دکمه‌ی «ورود» در نوار بالا کارت می‌زنند؛ بقیه را می‌توانید دستی ثبت کنید." />
        ) : (
          <ul className="divide-y divide-border">
            {dayRecords.map((r) => (
              <li key={r.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                <div className="min-w-32 flex-1">
                  <p className="flex flex-wrap items-center gap-2 font-medium">
                    {r.employee.name}
                    {r.late_minutes > 0 ? <Badge tone="warning" dot>{toPersianDigits(r.late_minutes)} دقیقه تأخیر</Badge> : null}
                    {r.edited ? <Badge tone="info">ثبت مدیر</Badge> : null}
                  </p>
                  {r.shift ? <p className="text-xs text-text-muted">شیفت {time(r.shift.starts_at)} تا {time(r.shift.ends_at)}</p> : <p className="text-xs text-text-subtle">بدون شیفت برنامه‌ریزی‌شده</p>}
                  {r.note ? <p className="text-xs text-text-muted">{r.note}</p> : null}
                </div>
                <span className="flex items-center gap-1.5 text-sm"><LogIn className="size-3.5 text-success" aria-label="ورود" />{time(r.clock_in_at)}</span>
                <span className="flex items-center gap-1.5 text-sm">
                  <LogOut className="size-3.5 text-text-subtle" aria-label="خروج" />
                  {r.clock_out_at ? time(r.clock_out_at) : <span className="text-success">سر کار</span>}
                </span>
                <span className="tabular w-28 text-end text-sm font-semibold">{r.clock_out_at ? formatMinutes(r.minutes) : '—'}</span>
                <button type="button" onClick={() => setEditing(r)} aria-label={`اصلاح ${r.employee.name}`} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text"><Pencil className="size-4" /></button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <Dialog open={editing !== null} onClose={() => setEditing(null)} size="sm" title={editing === 'new' ? 'ثبت دستی ورود و خروج' : editing ? `اصلاح «${editing.employee.name}»` : ''}>
        {editing !== null ? (
          <AttendanceForm key={editing === 'new' ? 'new' : editing.id} record={editing === 'new' ? null : editing} employees={employees} day={day} timezone={timezone} onDone={() => setEditing(null)} />
        ) : null}
      </Dialog>
    </div>
  );
}

function nowHHMM(timezone: string): string {
  return isoToLocal(new Date().toISOString(), timezone).time;
}

function AttendanceForm({ record, employees, day, timezone, onDone }: { record: AttendanceRecord | null; employees: Employee[]; day: string; timezone: string; onDone: () => void }) {
  const inLocal = record ? isoToLocal(record.clock_in_at, timezone) : { date: day, time: '08:00' };
  const [inTime, setInTime] = useState(inLocal.time);
  // An open record being closed defaults to "now"; a new one to eight hours later.
  const [outTime, setOutTime] = useState(() => (record?.clock_out_at ? isoToLocal(record.clock_out_at, timezone).time : record ? nowHHMM(timezone) : '16:00'));
  const [hasOut, setHasOut] = useState(true);
  const [state, action, pending] = useActionState<FormState, FormData>(saveAttendance.bind(null, record?.id ?? null), { ok: false });
  const [error, setError] = useState<string | null>(null);
  const nextDay = outTime <= inTime;

  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      {record ? <input type="hidden" name="employee_id" value={record.employee.id} /> : (
        <SelectField label="کارمند" name="employee_id" error={state.errors?.employee_id}>{employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}</SelectField>
      )}
      <input type="hidden" name="clock_in_at" value={localToIso(inLocal.date, inTime, timezone)} />
      {hasOut ? <input type="hidden" name="clock_out_at" value={localToIso(nextDay ? addDays(inLocal.date, 1) : inLocal.date, outTime, timezone)} /> : null}
      <div className="flex flex-wrap items-end gap-3 rounded-xl bg-surface-muted p-3">
        <div className="flex flex-col gap-1">
          <span className="text-xs text-text-muted">ورود</span>
          <span className="rounded-lg border border-border-strong bg-surface px-2 py-1.5"><ClockSelect label="ساعت ورود" value={inTime} onChange={setInTime} minuteStep={5} /></span>
        </div>
        {hasOut ? (
          <div className="flex flex-col gap-1">
            <span className="text-xs text-text-muted">خروج{nextDay ? ' (روز بعد)' : ''}</span>
            <span className="rounded-lg border border-border-strong bg-surface px-2 py-1.5"><ClockSelect label="ساعت خروج" value={outTime} onChange={setOutTime} minuteStep={5} /></span>
          </div>
        ) : null}
      </div>
      {state.errors?.clock_in_at || state.errors?.clock_out_at ? <p className="text-sm text-danger">{state.errors.clock_in_at ?? state.errors.clock_out_at}</p> : null}
      <Checkbox checked={!hasOut} onChange={(ev) => setHasOut(!ev.target.checked)} label="هنوز سر کار است (خروج ثبت نشود)" />
      <TextField label="دلیل / یادداشت" name="note" maxLength={200} defaultValue={record?.note ?? ''} placeholder="مثلاً کارت نزد، خروج را فراموش کرد" />
      <div className="flex items-center gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending}>ثبت</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
        {record ? (
          <button type="button" className="ms-auto inline-flex items-center gap-1 text-sm text-danger hover:underline"
            onClick={async () => { const r = await deleteAttendance(record.id); if (r.ok) onDone(); else setError(r.message ?? 'حذف نشد.'); }}>
            <Trash2 className="size-4" aria-hidden="true" />حذف
          </button>
        ) : null}
      </div>
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
    </form>
  );
}
