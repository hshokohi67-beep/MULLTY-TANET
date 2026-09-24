'use client';

import { useActionState, useState } from 'react';
import { Button, Card, CardHeader, ClockSelect } from '@cafe/ui';
import { IRANIAN_WEEK, WEEKDAY_LABELS, type IsoWeekday } from '@cafe/locale';
import { saveOpeningHours } from '@/app/actions/dashboard';
import { FormStatus } from '@/components/FormStatus';
import type { FormState, OpeningHour } from '@/lib/types';

interface Interval {
  opens_at: string;
  closes_at: string;
}

type Schedule = Record<IsoWeekday, Interval[]>;

const MAX_PER_DAY = 3;

function toSchedule(hours: OpeningHour[]): Schedule {
  const schedule = Object.fromEntries(IRANIAN_WEEK.map((d) => [d, [] as Interval[]])) as unknown as Schedule;
  for (const h of hours) schedule[h.weekday].push({ opens_at: h.opens_at, closes_at: h.closes_at });

  return schedule;
}

export function OpeningHoursEditor({ branchId, initial, readOnly }: { branchId: string; initial: OpeningHour[]; readOnly: boolean }) {
  const [schedule, setSchedule] = useState<Schedule>(() => toSchedule(initial));
  const [state, action, pending] = useActionState<FormState, FormData>(saveOpeningHours.bind(null, branchId), { ok: false });

  const update = (day: IsoWeekday, intervals: Interval[]) => setSchedule((s) => ({ ...s, [day]: intervals }));

  const copyToAll = (day: IsoWeekday) =>
    setSchedule((s) => Object.fromEntries(IRANIAN_WEEK.map((d) => [d, s[day].map((i) => ({ ...i }))])) as unknown as Schedule);

  const payload = IRANIAN_WEEK.flatMap((weekday) => schedule[weekday].map((i) => ({ weekday, ...i })));

  return (
    <Card>
      <CardHeader
        title="ساعات کاری"
        description="اگر ساعت پایان کمتر از ساعت شروع باشد، یعنی شعبه تا بامداد روز بعد باز است (مثلاً ۱۸:۰۰ تا ۰۲:۰۰)."
      />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <input type="hidden" name="intervals" value={JSON.stringify(payload)} />

        <fieldset disabled={readOnly || pending} className="flex flex-col divide-y divide-border">
          <legend className="sr-only">ساعات کاری هفته</legend>
          {IRANIAN_WEEK.map((day) => {
            const intervals = schedule[day];
            const closed = intervals.length === 0;

            return (
              <div key={day} className="flex flex-wrap items-center gap-3 py-3">
                <span className="w-20 shrink-0 text-sm font-medium" id={`day-${day}`}>{WEEKDAY_LABELS[day]}</span>

                {closed ? (
                  <span className="text-sm text-text-muted">تعطیل</span>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {intervals.map((interval, index) => (
                      <div key={index} className="flex items-center gap-1.5 rounded-md border border-border px-2 py-1">
                        <ClockSelect
                          label={`ساعت شروع ${WEEKDAY_LABELS[day]}`}
                          value={interval.opens_at}
                          disabled={readOnly || pending}
                          onChange={(v) => update(day, intervals.map((it, i) => (i === index ? { ...it, opens_at: v } : it)))}
                        />
                        <span className="text-xs text-text-muted" aria-hidden="true">تا</span>
                        <ClockSelect
                          label={`ساعت پایان ${WEEKDAY_LABELS[day]}`}
                          value={interval.closes_at}
                          disabled={readOnly || pending}
                          onChange={(v) => update(day, intervals.map((it, i) => (i === index ? { ...it, closes_at: v } : it)))}
                        />
                        <button
                          type="button"
                          onClick={() => update(day, intervals.filter((_, i) => i !== index))}
                          className="ms-1 rounded px-1 text-text-muted hover:text-danger"
                          aria-label={`حذف بازه‌ی ${WEEKDAY_LABELS[day]}`}
                        >
                          ×
                        </button>
                      </div>
                    ))}
                  </div>
                )}

                {!readOnly ? (
                  <div className="ms-auto flex gap-1">
                    {intervals.length < MAX_PER_DAY ? (
                      <Button size="sm" variant="ghost" onClick={() => update(day, [...intervals, { opens_at: closed ? '08:00' : '17:00', closes_at: closed ? '23:00' : '22:00' }])}>
                        {closed ? 'باز کن' : 'افزودن بازه'}
                      </Button>
                    ) : null}
                    {!closed ? <Button size="sm" variant="ghost" onClick={() => copyToAll(day)}>اعمال به همه‌ی روزها</Button> : null}
                  </div>
                ) : null}
              </div>
            );
          })}
        </fieldset>

        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره‌ی ساعات کاری</Button></div> : null}
      </form>
    </Card>
  );
}
