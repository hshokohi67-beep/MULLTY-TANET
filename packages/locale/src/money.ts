import { formatNumber } from './number.ts';

/**
 * Money arrives from the API as integer **rial** (never floats, never pre-formatted).
 * This is the only place the frontend turns it into text.
 */
export type CurrencyUnit = 'toman' | 'rial';

const UNIT_LABEL: Record<CurrencyUnit, string> = { toman: 'تومان', rial: 'ریال' };
const RIAL_FACTOR: Record<CurrencyUnit, number> = { toman: 10, rial: 1 };

export interface FormatMoneyOptions {
  unit?: CurrencyUnit;
  withUnit?: boolean;
}

/** 1250000 (rial) → '۱۲۵٬۰۰۰ تومان' */
export function formatMoney(rials: number, { unit = 'toman', withUnit = true }: FormatMoneyOptions = {}): string {
  if (!Number.isSafeInteger(rials)) {
    throw new TypeError('Money must be an integer amount of rials.');
  }

  const factor = RIAL_FACTOR[unit];
  const hasFraction = rials % factor !== 0;
  const text = formatNumber(rials / factor, hasFraction ? 1 : 0).replace('-', '−');

  return withUnit ? `${text} ${UNIT_LABEL[unit]}` : text;
}

/** User typed 125000 toman → 1250000 rial for the API. */
export function toRials(amount: number, unit: CurrencyUnit = 'toman'): number {
  const rials = Math.round(amount * RIAL_FACTOR[unit]);

  if (!Number.isSafeInteger(rials)) {
    throw new RangeError('Amount is out of range.');
  }

  return rials;
}

export function currencyLabel(unit: CurrencyUnit): string {
  return UNIT_LABEL[unit];
}

/**
 * Short money for chart axes and tiles: 25_000_000 rial → '۲٫۵ میلیون تومان' (or without the
 * unit). At most one decimal, trailing ".0" dropped.
 */
export function formatMoneyCompact(rials: number, { withUnit = true }: { withUnit?: boolean } = {}): string {
  const toman = rials / 10;
  const abs = Math.abs(toman);
  const [value, suffix] = abs >= 1_000_000_000 ? [toman / 1_000_000_000, ' میلیارد'] : abs >= 1_000_000 ? [toman / 1_000_000, ' میلیون'] : abs >= 1_000 ? [toman / 1_000, ' هزار'] : [toman, ''];
  const rounded = Math.round(value * 10) / 10;
  const text = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: Number.isInteger(rounded) ? 0 : 1 }).format(rounded).replace('-', '−');

  return `${text}${suffix}${withUnit ? ' تومان' : ''}`;
}
