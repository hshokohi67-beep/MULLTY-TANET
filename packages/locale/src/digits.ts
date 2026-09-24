const PERSIAN = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC = '٠١٢٣٤٥٦٧٨٩';

/** '۰۹۱۲' / '٠٩١٢' → '0912'. Use on user input before sending it to the API. */
export function toLatinDigits(value: string): string {
  return value.replace(/[۰-۹٠-٩]/g, (d) => {
    const persian = PERSIAN.indexOf(d);
    return String(persian >= 0 ? persian : ARABIC.indexOf(d));
  });
}

/** '0912' → '۰۹۱۲'. Presentation only; never store the result. */
export function toPersianDigits(value: string | number): string {
  return String(value).replace(/[0-9]/g, (d) => PERSIAN[Number(d)]);
}
