'use client';

import { useEffect, useRef, type ReactNode } from 'react';
import { cx } from '@cafe/ui';
import type { LandingDesign } from '@/lib/landing-types';
import { samim } from './fonts';

/**
 * The landing page's root: carries the look options as data attributes (styled in globals.css)
 * and switches scroll reveals on. Reveals are opt-in via the `l-js` class, so without JavaScript
 * (or with reduced motion) everything is simply visible. New sections (live preview) are picked
 * up by a MutationObserver.
 */
export function LandingFrame({ design, brandCss, className, children }: { design: LandingDesign; brandCss: string | null; className?: string; children: ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const root = ref.current;
    if (!root || design.motion === 'none' || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
      root?.classList.remove('l-js');
      return;
    }

    const io = new IntersectionObserver((entries) => {
      for (const e of entries) {
        if (e.isIntersecting) {
          e.target.classList.add('is-in');
          io.unobserve(e.target);
        }
      }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    const watch = (scope: ParentNode) => scope.querySelectorAll('[data-reveal]:not(.is-in)').forEach((el) => io.observe(el));

    watch(root);
    root.classList.add('l-js');
    const mo = new MutationObserver(() => watch(root));
    mo.observe(root, { childList: true, subtree: true });

    return () => { io.disconnect(); mo.disconnect(); };
  }, [design.motion]);

  return (
    <div
      ref={ref}
      className={cx('landing @container', samim.variable, className)}
      data-template={design.template}
      data-theme={design.template === 'night' ? 'dark' : undefined}
      data-font={design.font}
      data-type={design.type}
      data-texture={design.texture}
      data-corners={design.corners}
      data-motion={design.motion}
      data-hero={design.hero}
    >
      {brandCss ? <style dangerouslySetInnerHTML={{ __html: brandCss }} /> : null}
      {children}
    </div>
  );
}
