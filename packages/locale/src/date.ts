/**
 * Jalali (Solar Hijri) presentation via the built-in Intl persian calendar.
 * Inputs are ISO strings / Dates from the API (UTC). The tenant timezone decides the calendar day.
 */
export const DEFAULT_TIMEZONE = 'Asia/Tehran';

type DateInput = string | number | Date;

function toDate(value: DateInput): Date {
  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    throw new RangeError(`Invalid date: ${String(value)}`);
  }

  return date;
}

/** '2026-09-24T10:00:00Z' → '۱۴۰۵/۰۷/۰۲' */
export function formatJalaliDate(value: DateInput, timeZone = DEFAULT_TIMEZONE): string {
  const parts = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(toDate(value));

  const get = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';

  return `${get('year')}/${get('month')}/${get('day')}`;
}

/** '2026-09-24T10:00:00Z' → '۲ مهر ۱۴۰۵' */
export function formatJalaliLong(value: DateInput, timeZone = DEFAULT_TIMEZONE, withWeekday = false): string {
  // Assembled from parts: engines disagree on the order and punctuation once a weekday is added
  // (Chrome gives '۱۴۰۵ مهر ۲, پنجشنبه').
  const parts = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    timeZone,
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long',
  }).formatToParts(toDate(value));
  const get = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';
  const date = `${get('day')} ${get('month')} ${get('year')}`;

  return withWeekday ? `${get('weekday')} ${date}` : date;
}

/** '2026-09-24T10:05:00Z' → '۱۳:۳۵' (Tehran) */
export function formatTime(value: DateInput, timeZone = DEFAULT_TIMEZONE): string {
  return new Intl.DateTimeFormat('fa-IR', { timeZone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(toDate(value));
}

/** '۱۴۰۵/۰۷/۰۲ ساعت ۱۳:۳۵' */
export function formatJalaliDateTime(value: DateInput, timeZone = DEFAULT_TIMEZONE): string {
  return `${formatJalaliDate(value, timeZone)} ساعت ${formatTime(value, timeZone)}`;
}

/** A wall-clock time string from the API ('08:00') shown with Persian digits. */
export function formatClock(hhmm: string): string {
  return hhmm.slice(0, 5).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}
