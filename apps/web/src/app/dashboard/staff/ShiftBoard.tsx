'use client';

import Link from 'next/link';
import { useActionState, useEffect, useMemo, useState } from 'react';
import { CalendarPlus, Copy, Moon, Plus, Trash2, Users } from 'lucide-react';
import { Button, ClockSelect, cx, Dialog, EmptyState, SelectField, TextField } from '@cafe/ui';
import { addDays, formatNumber, gregorianToJalali, toPersianDigits, WEEKDAY_LABELS, type IsoWeekday } from '@cafe/locale';
import { copyWeek, deleteShift, saveShift } from '@/app/actions/operations';
import { FormStatus } from '@/components/FormStatus';
import { formatHours, isoToLocal, localToIso, type Employee, type Shift } from '@/lib/operations-types';
import type { FormState } from '@/lib/types';

const isoDay = (date: string): IsoWeekday => {
  const d = new Date(`${date}T12:00:00Z`).getUTCDay();

  return (d === 0 ? 7 : d) as IsoWeekday;
};
const faTime = (hhmm: string) => toPersianDigits(hhmm);

type Draft = { shift: Shift | null; employeeId: string; date: string };

/**
 * The week's plan: one row per person, one column per day (Saturday first). Click an empty cell
 * to add a shift, a shift to edit it; overnight shifts sit on the day they start.
 */
export function ShiftBoard({ employees, shifts, weekStart, today, timezone }: { employees: Employee[]; shifts: Shift[]; weekStart: string; today: string; timezone: string }) {
  const days = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)), [weekStart]);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [last, setLast] = useState({ start: '08:00', end: '16:00' });
  const [copy, setCopy] = useState<{ busy: boolean; message: string | null; ok: boolean }>({ busy: false, message: null, ok: true });

  const cells = useMemo(() => {
    const map = new Map<string, Shift[]>();
    for (const s of shifts) {
      const k = `${s.employee_id}|${isoToLocal(s.starts_at, timezone).date}`;
      map.set(k, [...(map.get(k) ?? []), s]);
    }

    return map;
  }, [shifts, timezone]);
  const minutesOf = (employeeId: string) => shifts.filter((s) => s.employee_id === employeeId).reduce((t, s) => t + s.minutes, 0);
  const dayMinutes = (date: string) => shifts.filter((s) => isoToLocal(s.starts_at, timezone).date === date).reduce((t, s) => t + s.minutes, 0);

  if (employees.length === 0) {
    return (
      <div className="rounded-2xl border border-border bg-surface">
        <EmptyState icon={<Users />} title="هنوز کارمندی تعریف نشده" description="اول در بخش «کارکنان» افراد را با نرخ دستمزد اضافه کنید؛ بعد برایشان شیفت بچینید."
          action={<Link href="/dashboard/staff?tab=team" className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand px-3.5 text-sm font-semibold text-on-brand hover:bg-brand-strong"><Plus className="size-4" aria-hidden="true" />افزودن کارمند</Link>} />
      </div>
    );
  }

  const onCopy = async () => {
    setCopy({ busy: true, message: null, ok: true });
    const r = await copyWeek(weekStart, addDays(weekStart, 7), null);
    setCopy({
      busy: false,
      ok: r.ok,
      message: r.ok
        ? (r.copied ? `${formatNumber(r.copied)} شیفت به هفته‌ی بعد کپی شد` : 'شیفت تازه‌ای برای کپی نبود') + (r.skipped ? `؛ ${formatNumber(r.skipped)} مورد تکراری یا متداخل رد شد.` : '.')
        : (r.message ?? 'کپی نشد.'),
    });
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-sm text-text-muted">مجموع برنامه‌ی هفته: <span className="tabular font-semibold text-text">{formatHours(shifts.reduce((t, s) => t + s.minutes, 0))}</span> ساعت</p>
        <Button variant="secondary" size="sm" icon={<Copy />} loading={copy.busy} onClick={onCopy} disabled={shifts.length === 0} className="ms-auto">کپی به هفته‌ی بعد</Button>
      </div>
      {copy.message ? (
        <p role="status" className={cx('rounded-lg px-3 py-2 text-sm', copy.ok ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger')}>
          {copy.message}{copy.ok ? <> <Link href={`/dashboard/staff?tab=schedule&week=${addDays(weekStart, 7)}`} className="font-semibold underline">دیدن هفته‌ی بعد</Link></> : null}
        </p>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)]">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[56rem] table-fixed border-collapse text-sm">
            <colgroup><col className="w-40" />{days.map((d) => <col key={d} />)}<col className="w-20" /></colgroup>
            <thead>
              <tr className="border-b border-border">
                <th className="sticky start-0 z-10 bg-surface px-3 py-2.5 text-start text-xs font-medium text-text-muted">نام</th>
                {days.map((d) => {
                  const j = gregorianToJalali(d);

                  return (
                    <th key={d} className={cx('px-1.5 py-2 text-center font-medium', d === today && 'bg-brand-soft/50')}>
                      <span className={cx('block text-xs', d === today ? 'font-semibold text-brand-strong' : 'text-text-muted')}>{WEEKDAY_LABELS[isoDay(d)]}</span>
                      <span className="tabular block text-[11px] text-text-subtle">{toPersianDigits(j.day)}/{toPersianDigits(j.month)}</span>
                    </th>
                  );
                })}
                <th className="px-2 py-2.5 text-center text-xs font-medium text-text-muted">جمع</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {employees.map((e) => (
                <tr key={e.id}>
                  <th scope="row" className="sticky start-0 z-10 bg-surface px-3 py-2 text-start font-normal">
                    <span className="block truncate font-medium">{e.name}</span>
                    {e.position ? <span className="block truncate text-xs text-text-muted">{e.position}</span> : null}
                  </th>
                  {days.map((d) => {
                    const list = cells.get(`${e.id}|${d}`) ?? [];

                    return (
                      <td key={d} className={cx('group p-1 align-top', d === today && 'bg-brand-soft/25')}>
                        <div className="flex min-h-12 flex-col gap-1">
                          {list.map((s) => {
                            const start = isoToLocal(s.starts_at, timezone);
                            const end = isoToLocal(s.ends_at, timezone);

                            return (
                              <button key={s.id} type="button" onClick={() => setDraft({ shift: s, employeeId: e.id, date: d })} title={s.note ?? undefined}
                                className="flex flex-col rounded-lg border border-brand/25 bg-brand-soft px-1.5 py-1 text-start text-brand-strong transition-colors hover:border-brand">
                                <span dir="ltr" className="tabular text-end text-xs font-semibold">{faTime(start.time)}–{faTime(end.time)}</span>
                                {end.date !== start.date ? <span className="flex items-center gap-0.5 text-[10px] opacity-80"><Moon className="size-2.5" aria-hidden="true" />تا فردا</span> : null}
                                {s.note ? <span className="truncate text-[10px] opacity-80">{s.note}</span> : null}
                              </button>
                            );
                          })}
                          <button type="button" onClick={() => setDraft({ shift: null, employeeId: e.id, date: d })} aria-label={`افزودن شیفت ${e.name}، ${WEEKDAY_LABELS[isoDay(d)]}`}
                            className={cx('flex flex-1 items-center justify-center rounded-lg border border-dashed border-border text-text-subtle transition-opacity hover:border-brand hover:text-brand focus-visible:opacity-100',
                              list.length ? 'min-h-6 opacity-0 group-hover:opacity-100' : 'min-h-11 opacity-40 group-hover:opacity-100')}>
                            <Plus className="size-3.5" />
                          </button>
                        </div>
                      </td>
                    );
                  })}
                  <td className="tabular px-2 text-center text-xs font-semibold text-text-muted">{minutesOf(e.id) ? formatHours(minutesOf(e.id)) : '—'}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t border-border bg-surface-muted/40 text-xs text-text-muted">
                <th scope="row" className="sticky start-0 bg-surface-muted px-3 py-2 text-start font-medium">ساعت در روز</th>
                {days.map((d) => <td key={d} className="tabular py-2 text-center">{dayMinutes(d) ? formatHours(dayMinutes(d)) : '—'}</td>)}
                <td />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <Dialog open={draft !== null} onClose={() => setDraft(null)} size="sm" title={draft?.shift ? 'ویرایش شیفت' : 'شیفت جدید'}
        description={draft ? `${employees.find((e) => e.id === draft.employeeId)?.name ?? ''} • ${WEEKDAY_LABELS[isoDay(draft.date)]}` : undefined}>
        {draft ? (
          <ShiftForm key={draft.shift?.id ?? `${draft.employeeId}${draft.date}`} draft={draft} employees={employees} days={days} timezone={timezone} defaults={last}
            onDone={(used) => { if (used) setLast(used); setDraft(null); }} />
        ) : null}
      </Dialog>
    </div>
  );
}

function ShiftForm({ draft, employees, days, timezone, defaults, onDone }: {
  draft: Draft; employees: Employee[]; days: string[]; timezone: string; defaults: { start: string; end: string }; onDone: (used?: { start: string; end: string }) => void;
}) {
  const initial = draft.shift
    ? { date: isoToLocal(draft.shift.starts_at, timezone).date, start: isoToLocal(draft.shift.starts_at, timezone).time, end: isoToLocal(draft.shift.ends_at, timezone).time }
    : { date: draft.date, ...defaults };
  const [employeeId, setEmployeeId] = useState(draft.employeeId);
  const [date, setDate] = useState(initial.date);
  const [start, setStart] = useState(initial.start);
  const [end, setEnd] = useState(initial.end);
  const [state, action, pending] = useActionState<FormState, FormData>(saveShift.bind(null, draft.shift?.id ?? null), { ok: false });
  const [error, setError] = useState<string | null>(null);

  const overnight = end <= start;
  const minutes = ((Number(end.slice(0, 2)) * 60 + Number(end.slice(3))) - (Number(start.slice(0, 2)) * 60 + Number(start.slice(3))) + (overnight ? 1440 : 0));

  useEffect(() => { if (state.ok) onDone({ start, end }); }, [state.ok]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      <input type="hidden" name="starts_at" value={localToIso(date, start, timezone)} />
      <input type="hidden" name="ends_at" value={localToIso(overnight ? addDays(date, 1) : date, end, timezone)} />
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="کارمند" name="employee_id" value={employeeId} onChange={(ev) => setEmployeeId(ev.target.value)} error={state.errors?.employee_id}>
          {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
        </SelectField>
        <SelectField label="روز" value={date} onChange={(ev) => setDate(ev.target.value)}>
          {(days.includes(date) ? days : [date, ...days]).map((d) => {
            const j = gregorianToJalali(d);

            return <option key={d} value={d}>{WEEKDAY_LABELS[isoDay(d)]} {toPersianDigits(j.day)}/{toPersianDigits(j.month)}</option>;
          })}
        </SelectField>
      </div>
      <div className="flex items-end gap-3 rounded-xl bg-surface-muted p-3">
        <div className="flex flex-col gap-1">
          <span className="text-xs text-text-muted">شروع</span>
          <span className="rounded-lg border border-border-strong bg-surface px-2 py-1.5"><ClockSelect label="ساعت شروع" value={start} onChange={setStart} minuteStep={15} /></span>
        </div>
        <span className="pb-2 text-text-subtle">تا</span>
        <div className="flex flex-col gap-1">
          <span className="text-xs text-text-muted">پایان</span>
          <span className="rounded-lg border border-border-strong bg-surface px-2 py-1.5"><ClockSelect label="ساعت پایان" value={end} onChange={setEnd} minuteStep={15} /></span>
        </div>
        <span className="ms-auto whitespace-nowrap pb-1.5 text-end text-xs">
          <span className={cx('tabular block font-semibold', minutes > 16 * 60 && 'text-danger')}>{formatHours(minutes)} ساعت</span>
          {overnight ? <span className="flex items-center gap-1 text-text-muted"><Moon className="size-3" aria-hidden="true" />تا روز بعد</span> : null}
        </span>
      </div>
      {state.errors?.starts_at || state.errors?.ends_at ? <p className="text-sm text-danger">{state.errors.starts_at ?? state.errors.ends_at}</p> : null}
      <TextField label="یادداشت (اختیاری)" name="note" maxLength={120} defaultValue={draft.shift?.note ?? ''} placeholder="مثلاً صندوق، بار" />
      <div className="flex items-center gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending} icon={<CalendarPlus />}>{draft.shift ? 'ذخیره' : 'افزودن شیفت'}</Button>
        <Button variant="ghost" onClick={() => onDone()}>انصراف</Button>
        {draft.shift ? (
          <button type="button" className="ms-auto inline-flex items-center gap-1 text-sm text-danger hover:underline"
            onClick={async () => { const r = await deleteShift(draft.shift!.id); if (r.ok) onDone(); else setError(r.message ?? 'حذف نشد.'); }}>
            <Trash2 className="size-4" aria-hidden="true" />حذف
          </button>
        ) : null}
      </div>
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
    </form>
  );
}
