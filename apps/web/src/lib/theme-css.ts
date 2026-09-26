import { brandTokens } from '@cafe/ui';

/**
 * The café's brand colour re-derived for the dark palette, scoped to one selector: for surfaces
 * that are always dark (the «شب» look) while the page itself may be light.
 */
export function darkBrandCss(hex: string | null | undefined, selector: string): string | null {
  const t = brandTokens(hex, 'dark');

  return t ? `${selector}{--color-brand:${t.brand};--color-brand-strong:${t.brandStrong};--color-brand-soft:${t.brandSoft};--color-on-brand:${t.onBrand};}` : null;
}
