import { toLatinDigits, toRials } from '@cafe/locale';

/**
 * Parses what a person typed into a toman field ("۸۵٬۰۰۰", "85,000", "85000") into integer rial.
 * Returns null for empty input and NaN for anything that isn't a whole number.
 */
export function parseTomanInput(value: FormDataEntryValue | string | null | undefined): number | null {
  if (typeof value !== 'string') return null;

  const cleaned = toLatinDigits(value).replace(/[\s,٬_]/g, '');

  if (cleaned === '') return null;
  if (!/^\d+$/.test(cleaned)) return Number.NaN;

  return toRials(Number(cleaned), 'toman');
}

/** Integer rial → the plain toman number shown in an input ("85000"). */
export function rialToTomanInput(rials: number | null | undefined): string {
  return rials == null ? '' : String(rials / 10);
}
