'use client';

import { useState } from 'react';
import { gregorianToJalali, JALALI_MONTHS, jalaliDaysInMonth, jalaliToGregorian, toPersianDigits, todayIn } from '@cafe/locale';

/**
 * A Jalali date picker made of three selects (day • month • year). Never a native date input:
 * those follow the browser locale and show Gregorian/English. Submits a Gregorian "YYYY-MM-DD"
 * under `name`, which is what the API expects.
 */
export function JalaliDateField({ label, name, defaultValue, years = 3 }: { label: string; name: string; defaultValue?: string; years?: number }) {
  const initial = gregorianToJalali(defaultValue || todayIn());
  const [year, setYear] = useState(initial.year);
  const [month, setMonth] = useState(initial.month);
  const [day, setDay] = useState(initial.day);
  const thisYear = gregorianToJalali(todayIn()).year;
  const maxDay = jalaliDaysInMonth(year, month);
  const safeDay = Math.min(day, maxDay);
  const select = 'h-10 rounded-md border border-border-strong bg-surface px-2 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none';

  return (
    <fieldset className="flex flex-col gap-1.5">
      <legend className="mb-1.5 text-sm font-medium">{label}</legend>
      <div className="flex gap-1.5">
        <select aria-label={`${label}: روز`} value={safeDay} onChange={(e) => setDay(Number(e.target.value))} className={select}>
          {Array.from({ length: maxDay }, (_, i) => i + 1).map((d) => <option key={d} value={d}>{toPersianDigits(d)}</option>)}
        </select>
        <select aria-label={`${label}: ماه`} value={month} onChange={(e) => setMonth(Number(e.target.value))} className={`${select} flex-1`}>
          {JALALI_MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
        </select>
        <select aria-label={`${label}: سال`} value={year} onChange={(e) => setYear(Number(e.target.value))} className={select}>
          {Array.from({ length: years }, (_, i) => thisYear - i).map((y) => <option key={y} value={y}>{toPersianDigits(y)}</option>)}
        </select>
      </div>
      <input type="hidden" name={name} value={jalaliToGregorian({ year, month, day: safeDay })} />
    </fieldset>
  );
}
