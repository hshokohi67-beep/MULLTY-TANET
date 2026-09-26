import type { ComponentType } from 'react';
import { AtSign, UtensilsCrossed } from 'lucide-react';
import { brandTokens, Ltr } from '@cafe/ui';
import type { PublicLanding, SectionKey } from '@/lib/landing-types';
import type { Menu, Storefront } from '@/lib/storefront-types';
import { LandingFrame } from './LandingFrame';
import { LandingHeader, LandingHero } from './LandingHero';
import { FeaturedSection, GallerySection, HighlightsSection, MarqueeSection, StorySection, VisitSection, type SectionProps } from './LandingSections';
import { Go } from './parts';

const SECTIONS: Record<SectionKey, ComponentType<SectionProps>> = {
  story: StorySection,
  highlights: HighlightsSection,
  featured: FeaturedSection,
  marquee: MarqueeSection,
  gallery: GallerySection,
  visit: VisitSection,
};

/**
 * A café's landing page: header, hero, the sections it switched on (in its order) and a footer.
 * Pure rendering from public data, so the panel can preview it live with the same components
 * (`preview` turns links into text). The night template always uses the dark palette, so the
 * café's brand colour is re-derived for dark there.
 */
export function Landing({ tenant, store, landing, menu, preview = false }: { tenant: string; store: Storefront; landing: PublicLanding; menu: Menu | null; preview?: boolean }) {
  const dark = landing.design.template === 'night' ? brandTokens(store.branding?.primary_color, 'dark') : null;
  const css = dark ? `.landing[data-template='night']{--color-brand:${dark.brand};--color-brand-strong:${dark.brandStrong};--color-brand-soft:${dark.brandSoft};--color-on-brand:${dark.onBrand};}` : null;
  const props = { tenant, store, landing, menu, preview };

  return (
    <LandingFrame design={landing.design} brandCss={css} className="flex min-h-dvh flex-col">
      <LandingHeader {...props} />
      <main id={preview ? undefined : 'main'} className="flex-1">
        <LandingHero {...props} />
        {landing.sections.filter((s) => s.visible).map((s) => {
          const Section = SECTIONS[s.key];

          return <Section key={s.key} {...props} />;
        })}
      </main>

      <footer className="overflow-hidden border-t border-border pb-28 pt-14 text-center">
        <p aria-hidden="true" className="l-display select-none truncate px-2 text-[17cqw] leading-none opacity-[0.07] @xl:text-[12cqw]">{store.name}</p>
        <div className="mx-auto mt-6 flex max-w-6xl flex-wrap items-center justify-center gap-3 px-5">
          <Go href={`/s/${tenant}/menu`} preview={preview} className="l-pill inline-flex h-11 items-center gap-2 bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-strong">
            <UtensilsCrossed className="size-4" aria-hidden="true" />{landing.content.hero.cta_label}
          </Go>
          {store.contact.instagram ? (
            <Go href={`https://instagram.com/${store.contact.instagram}`} external preview={preview} className="l-pill inline-flex h-11 items-center gap-2 border border-border px-4 text-sm hover:bg-surface-muted">
              <AtSign className="size-4" aria-hidden="true" /><Ltr>{store.contact.instagram}</Ltr>
            </Go>
          ) : null}
        </div>
        <p className="mt-8 text-xs text-text-subtle">ساخته‌شده با کافه‌یار</p>
      </footer>
    </LandingFrame>
  );
}
