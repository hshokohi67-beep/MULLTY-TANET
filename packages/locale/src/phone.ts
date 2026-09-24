import { toLatinDigits, toPersianDigits } from './digits.ts';

/**
 * Mirrors the backend PhoneNormalizer: any common Iranian mobile form → '+989XXXXXXXXX'.
 * The API is the authority; this is for instant client-side feedback only.
 */
export function normalizeIranianMobile(input: string): string | null {
  const raw = toLatinDigits(input.trim());
  const hasPlus = raw.startsWith('+');
  const digits = raw.replace(/[\s\-.()+‌‎‏]/g, '');

  if (!/^\d+$/.test(digits)) {
    return null;
  }

  let national = digits;

  if (digits.startsWith('0098')) national = digits.slice(4);
  else if (hasPlus && digits.startsWith('98')) national = digits.slice(2);
  else if (digits.length === 12 && digits.startsWith('98')) national = digits.slice(2);
  else if (digits.length === 11 && digits.startsWith('0')) national = digits.slice(1);

  return /^9\d{9}$/.test(national) ? `+98${national}` : null;
}

/** '09121234567' or '+989121234567' → '۰۹۱۲ ۱۲۳ ۴۵۶۷' */
export function formatPhone(value: string): string {
  const e164 = normalizeIranianMobile(value);

  if (!e164) {
    return toPersianDigits(value);
  }

  const local = `0${e164.slice(3)}`;

  return toPersianDigits(`${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}`);
}

/** '1996835111' → '۱۹۹۶۸-۳۵۱۱۱' */
export function formatPostalCode(value: string): string {
  const digits = toLatinDigits(value).replace(/\D/g, '');

  return digits.length === 10 ? toPersianDigits(`${digits.slice(0, 5)}-${digits.slice(5)}`) : toPersianDigits(value);
}
