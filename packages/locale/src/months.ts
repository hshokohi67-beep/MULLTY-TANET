/** Jalali month names, index 0 = Farvardin (month 1). */
export const JALALI_MONTHS = [
  'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
  'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
] as const;

/** Days in a Jalali month, ignoring leap years (Esfand is allowed 30 so a birthday on the 30th can be stored). */
export function jalaliMonthDays(month: number): number {
  return month <= 6 ? 31 : 30;
}
