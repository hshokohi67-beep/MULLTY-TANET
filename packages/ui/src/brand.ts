/**
 * Turns a tenant's brand colour into the storefront's brand tokens. Any colour a café picks must
 * stay readable: text on the brand fill switches between white and near-black by WCAG contrast,
 * and a colour too light to be a link or button on paper is darkened until it reaches 3:1.
 */

type Rgb = [number, number, number];

const HEX = /^#?([0-9a-f]{6}|[0-9a-f]{3})$/i;

export function parseHex(hex: string | null | undefined): Rgb | null {
  const m = hex ? HEX.exec(hex.trim()) : null;
  if (!m) return null;
  const h = m[1].length === 3 ? m[1].split('').map((c) => c + c).join('') : m[1];

  return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16)) as Rgb;
}

const toHex = (rgb: Rgb) => `#${rgb.map((v) => Math.round(Math.min(255, Math.max(0, v))).toString(16).padStart(2, '0')).join('')}`;

/** WCAG relative luminance. */
export function luminance([r, g, b]: Rgb): number {
  const lin = (v: number) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };

  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

export function contrast(a: Rgb, b: Rgb): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

  return (hi + 0.05) / (lo + 0.05);
}

const mix = (a: Rgb, b: Rgb, t: number): Rgb => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t];

function toHsl([r, g, b]: Rgb): [number, number, number] {
  const [rn, gn, bn] = [r / 255, g / 255, b / 255];
  const max = Math.max(rn, gn, bn);
  const min = Math.min(rn, gn, bn);
  const l = (max + min) / 2;
  if (max === min) return [0, 0, l];
  const d = max - min;
  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
  const h = max === rn ? (gn - bn) / d + (gn < bn ? 6 : 0) : max === gn ? (bn - rn) / d + 2 : (rn - gn) / d + 4;

  return [h / 6, s, l];
}

function fromHsl(h: number, s: number, l: number): Rgb {
  if (s === 0) return [l * 255, l * 255, l * 255];
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  const hue = (t: number) => {
    const x = t < 0 ? t + 1 : t > 1 ? t - 1 : t;
    if (x < 1 / 6) return p + (q - p) * 6 * x;
    if (x < 1 / 2) return q;
    if (x < 2 / 3) return p + (q - p) * (2 / 3 - x) * 6;
    return p;
  };

  return [hue(h + 1 / 3) * 255, hue(h) * 255, hue(h - 1 / 3) * 255];
}

const WHITE: Rgb = [255, 255, 255];
const INK: Rgb = [29, 28, 26]; // --color-text
const PAPER: Rgb = [246, 245, 241]; // --color-bg
const CHARCOAL: Rgb = [18, 18, 17]; // dark --color-bg

export interface BrandTokens {
  brand: string;
  brandStrong: string;
  brandSoft: string;
  onBrand: string;
}

/**
 * Brand tokens for one theme, or null when the colour is missing/invalid (keep the default palette).
 * Light: darkened until it reaches 3:1 on paper. Dark: lightened until it reaches 3:1 on charcoal,
 * with a deep tinted "soft" fill instead of a pale one.
 */
export function brandTokens(hex: string | null | undefined, mode: 'light' | 'dark' = 'light'): BrandTokens | null {
  let rgb = parseHex(hex);
  if (!rgb) return null;

  const surface = mode === 'light' ? PAPER : CHARCOAL;
  const toward: Rgb = mode === 'light' ? [0, 0, 0] : WHITE;
  // Buttons and links need at least 3:1 against the page (non-text UI contrast). Adjust lightness
  // only, keeping hue and saturation: mixing with black would turn a pale mint into muddy grey.
  if (contrast(rgb, surface) < 3) {
    const [h, s, l0] = toHsl(rgb);
    const sat = s < 0.12 ? s : Math.max(s, 0.45); // greys stay grey; pastels regain some colour
    for (let l = l0; l >= 0 && l <= 1; l += mode === 'light' ? -0.01 : 0.01) {
      rgb = fromHsl(h, sat, l);
      if (contrast(rgb, surface) >= 3) break;
    }
  }

  // Light theme: brand buttons read best with white text; go a little deeper (never below 25%
  // lightness) when that makes white reach AA on the fill.
  if (mode === 'light' && contrast(rgb, WHITE) < 4.5) {
    const [h, s, l0] = toHsl(rgb);
    for (let l = l0; l >= 0.25; l -= 0.01) {
      const deeper = fromHsl(h, s, l);
      if (contrast(deeper, WHITE) >= 4.5) { rgb = deeper; break; }
    }
  }

  const onBrand = contrast(rgb, WHITE) >= 4.5 || contrast(rgb, WHITE) >= contrast(rgb, INK) ? WHITE : INK;

  return {
    brand: toHex(rgb),
    brandStrong: toHex(mix(rgb, toward, 0.18)),
    brandSoft: toHex(mode === 'light' ? mix(rgb, WHITE, 0.86) : mix(rgb, CHARCOAL, 0.78)),
    onBrand: toHex(onBrand),
  };
}

/** CSS that applies a tenant's brand colour to `selector` in both themes (null = keep defaults). */
export function brandCss(hex: string | null | undefined, selector: string): string | null {
  const light = brandTokens(hex, 'light');
  const dark = brandTokens(hex, 'dark');
  if (!light || !dark) return null;

  const vars = (t: BrandTokens) => `--color-brand:${t.brand};--color-brand-strong:${t.brandStrong};--color-brand-soft:${t.brandSoft};--color-on-brand:${t.onBrand};`;

  return `${selector}{${vars(light)}}`
    + `@media (prefers-color-scheme: dark){:root:not([data-theme='light']) ${selector}{${vars(dark)}}}`
    + `:root[data-theme='dark'] ${selector}{${vars(dark)}}`;
}
