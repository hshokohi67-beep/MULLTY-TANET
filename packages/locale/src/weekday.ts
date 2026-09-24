/** ISO weekday (1 = Monday … 7 = Sunday), matching the API. */
export type IsoWeekday = 1 | 2 | 3 | 4 | 5 | 6 | 7;

export const WEEKDAY_LABELS: Record<IsoWeekday, string> = {
  6: 'شنبه',
  7: 'یکشنبه',
  1: 'دوشنبه',
  2: 'سه‌شنبه',
  3: 'چهارشنبه',
  4: 'پنجشنبه',
  5: 'جمعه',
};

/** Iranian week order: Saturday first. */
export const IRANIAN_WEEK: readonly IsoWeekday[] = [6, 7, 1, 2, 3, 4, 5];
