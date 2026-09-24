import { toLatinDigits } from './digits.ts';

const CHAR_MAP: Record<string, string> = {
  'ي': 'ی', 'ى': 'ی', 'ئ': 'ی',
  'ك': 'ک',
  'ة': 'ه', 'ۀ': 'ه', 'ە': 'ه',
  'أ': 'ا', 'إ': 'ا', 'ٱ': 'ا',
  'ؤ': 'و',
};

/** Mirrors the backend PersianTextNormalizer::forSearch (client-side filtering). */
export function normalizeForSearch(text: string | null | undefined): string {
  if (!text) return '';

  return toLatinDigits(text.replace(/[يىئكةۀەأإٱؤ]/g, (c) => CHAR_MAP[c] ?? c))
    .replace(/[ً-ٰٟـ]/g, '')
    .replace(/[‌‍‎‏ ]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}
