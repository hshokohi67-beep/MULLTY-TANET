const integerFormatter = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });

/** 125000 → '۱۲۵٬۰۰۰' */
export function formatNumber(value: number, fractionDigits = 0): string {
  if (fractionDigits === 0) {
    return integerFormatter.format(value);
  }

  return new Intl.NumberFormat('fa-IR', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(value);
}

/** 0.125 → '۱۲٫۵٪' */
export function formatPercent(ratio: number, fractionDigits = 1): string {
  return new Intl.NumberFormat('fa-IR', { style: 'percent', maximumFractionDigits: fractionDigits }).format(ratio);
}
