'use client';

import { dealItems } from '@/lib/deals';
import type { PublicLanding } from '@/lib/landing-types';
import type { Menu, MenuProduct } from '@/lib/storefront-types';
import { storeHref } from '@/lib/store-links';
import { DealsProvider, useDeals } from '../deals/DealsProvider';
import { DealsSlider } from '../deals/DealsSlider';
import { ArtLayer, Heading } from './parts';

/**
 * «پیشنهاد امروز و تخفیف‌ها» on the landing page: the menu's own slider (same prices, same
 * countdowns), linking to each product's page. The whole section disappears while nothing is on.
 */
export function LandingDeals({ tenant, landing, menu, preview }: { tenant: string; landing: PublicLanding; menu: Menu; preview?: boolean }) {
  return (
    <DealsProvider menu={menu} tenant={tenant} sync={!preview}>
      <Section tenant={tenant} landing={landing} menu={menu} preview={preview} />
    </DealsProvider>
  );
}

function Section({ tenant, landing, menu, preview }: { tenant: string; landing: PublicLanding; menu: Menu; preview?: boolean }) {
  const deals = useDeals();
  if (dealItems(menu, deals).length === 0) return null;
  const { variant, title } = landing.content.deals;
  const href = preview ? undefined : (p: MenuProduct) => storeHref(`/s/${tenant}/p/${encodeURIComponent(p.slug)}`);

  return (
    <section id="deals" className="l-texture overflow-hidden py-16 @xl:py-24">
      <ArtLayer design={landing.design} seed={5} />
      <div className="mx-auto max-w-6xl px-5 @xl:px-8">
        <DealsSlider menu={menu} variant={variant} href={href}
          heading={<Heading eyebrow="همین حالا" title={title} />}
          rowClassName="-mx-5 scroll-px-5 px-5 @xl:-mx-8 @xl:scroll-px-8 @xl:px-8"
          cardClassName="w-[78%] max-w-[18rem] shrink-0 snap-start @xl:w-72"
          gridClassName="grid grid-cols-2 gap-4 @3xl:grid-cols-3 @5xl:grid-cols-4" />
      </div>
    </section>
  );
}
