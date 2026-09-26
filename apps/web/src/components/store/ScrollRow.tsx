'use client';

import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState, type ElementType, type ReactNode } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cx } from '@cafe/ui';

/**
 * A horizontal row that also scrolls with a mouse: arrow buttons appear on hover-capable screens
 * when there is more to see, and a vertical wheel scrolls it sideways (only while the row can
 * still move, so the page keeps scrolling at the ends). Touch screens just swipe.
 */
export const ScrollRow = forwardRef<HTMLElement, {
  as?: ElementType;
  className?: string;
  children: ReactNode;
  label?: string;
  /** Arrow placement: over the row's ends (default) or none. */
  arrows?: boolean;
  wrapperClassName?: string;
  /** Vertical position of the arrows (default: the middle), e.g. over a card's photo. */
  arrowTop?: string;
}>(function ScrollRow({ as: Tag = 'div', className, children, label, arrows = true, wrapperClassName, arrowTop = 'top-1/2' }, ref) {
  const inner = useRef<HTMLElement>(null);
  useImperativeHandle(ref, () => inner.current as HTMLElement);
  const [edges, setEdges] = useState({ start: false, end: false });

  // RTL: scrollLeft is 0 at the start (right) and negative towards the end (left).
  const measure = useCallback(() => {
    const el = inner.current;
    if (!el) return;
    const max = el.scrollWidth - el.clientWidth;
    const pos = Math.abs(el.scrollLeft);
    setEdges({ start: pos > 4, end: pos < max - 4 });
  }, []);

  useEffect(() => {
    const el = inner.current;
    if (!el) return;
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    el.addEventListener('scroll', measure, { passive: true });
    const onWheel = (e: WheelEvent) => {
      if (Math.abs(e.deltaY) <= Math.abs(e.deltaX) || e.ctrlKey) return;
      const max = el.scrollWidth - el.clientWidth;
      if (max <= 0) return;
      const pos = Math.abs(el.scrollLeft);
      const forward = e.deltaY > 0;
      if ((forward && pos >= max - 1) || (!forward && pos <= 0)) return; // at the end: let the page scroll
      e.preventDefault();
      const rtl = getComputedStyle(el).direction === 'rtl';
      el.scrollBy({ left: (rtl ? -1 : 1) * e.deltaY });
    };
    el.addEventListener('wheel', onWheel, { passive: false });

    return () => { ro.disconnect(); el.removeEventListener('scroll', measure); el.removeEventListener('wheel', onWheel); };
  }, [measure]);

  const page = (dir: 1 | -1) => {
    const el = inner.current;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';
    el.scrollBy({ left: dir * (rtl ? -1 : 1) * el.clientWidth * 0.8, behavior: 'smooth' });
  };

  const button = 'pointer-fine:flex absolute z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface text-text shadow-[var(--shadow-md)] transition-opacity hover:bg-surface-muted focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none';

  return (
    <div className={cx('group/row relative', wrapperClassName)}>
      <Tag ref={inner} aria-label={label} className={cx('no-scrollbar overflow-x-auto', className)}>{children}</Tag>
      {arrows && edges.start ? (
        <button type="button" onClick={() => page(-1)} aria-label="قبلی" className={cx(button, arrowTop, 'start-0')}><ChevronRight className="size-4" /></button>
      ) : null}
      {arrows && edges.end ? (
        <button type="button" onClick={() => page(1)} aria-label="بعدی" className={cx(button, arrowTop, 'end-0')}><ChevronLeft className="size-4" /></button>
      ) : null}
    </div>
  );
});
