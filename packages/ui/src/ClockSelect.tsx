'use client';

/**
 * 24-hour time picker with Persian digits. Replaces <input type="time">, whose
 * rendering follows the browser locale (e.g. "11:00 PM") and can't be forced to Persian.
 * Value format is always "HH:MM" with Latin digits (what the API expects).
 */
const PERSIAN = '۰۱۲۳۴۵۶۷۸۹';
const fa = (n: number) => String(n).padStart(2, '0').replace(/\d/g, (d) => PERSIAN[Number(d)]);

const HOURS = Array.from({ length: 24 }, (_, h) => h);

export interface ClockSelectProps {
  value: string;
  onChange: (value: string) => void;
  /** Accessible name, e.g. "ساعت شروع شنبه". */
  label: string;
  minuteStep?: 5 | 10 | 15 | 30;
  disabled?: boolean;
}

export function ClockSelect({ value, onChange, label, minuteStep = 5, disabled }: ClockSelectProps) {
  const [h, m] = value.split(':').map((part) => Number(part) || 0);
  const minutes = Array.from({ length: 60 / minuteStep }, (_, i) => i * minuteStep);
  // Keep an off-grid stored minute (e.g. 07) selectable instead of silently changing it.
  if (!minutes.includes(m)) minutes.push(m);
  minutes.sort((a, b) => a - b);

  const emit = (hour: number, minute: number) => onChange(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`);
  const select = 'rounded-sm bg-transparent px-0.5 text-sm focus-visible:outline-none focus-visible:shadow-[var(--focus-ring)]';

  return (
    <span role="group" aria-label={label} dir="ltr" className="inline-flex items-center gap-0.5">
      <select aria-label={`${label} (ساعت)`} className={select} value={h} disabled={disabled} onChange={(e) => emit(Number(e.target.value), m)}>
        {HOURS.map((hour) => <option key={hour} value={hour}>{fa(hour)}</option>)}
      </select>
      <span aria-hidden="true">:</span>
      <select aria-label={`${label} (دقیقه)`} className={select} value={m} disabled={disabled} onChange={(e) => emit(h, Number(e.target.value))}>
        {minutes.map((minute) => <option key={minute} value={minute}>{fa(minute)}</option>)}
      </select>
    </span>
  );
}
