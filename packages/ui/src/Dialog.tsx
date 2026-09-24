'use client';

import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { cx } from './cx.ts';

/**
 * Modal dialog and side drawer on the native <dialog> element: the browser provides the focus
 * trap, Esc to close, inert background and the top layer. We only add the look and motion.
 */
export function Dialog({ open, onClose, title, description, children, footer, variant = 'modal', size = 'md' }: {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  /** sheet: slides up from the bottom on phones, a centred modal on wider screens. */
  variant?: 'modal' | 'drawer' | 'sheet';
  size?: 'sm' | 'md' | 'lg';
}) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (open && !el.open) el.showModal();
    if (!open && el.open) el.close();
  }, [open]);

  const width = size === 'sm' ? 'max-w-sm' : size === 'lg' ? 'max-w-2xl' : 'max-w-lg';

  return (
    <dialog
      ref={ref}
      onClose={onClose}
      onClick={(e) => { if (e.target === ref.current) onClose(); }} // click on the backdrop
      className={cx(
        // The dialog never scrolls itself (only its body does); otherwise focusing a control while
        // the panel slides in scrolls the whole dialog and hides the header.
        'overflow-clip bg-transparent p-0 text-text backdrop:bg-black/40 backdrop:backdrop-blur-[2px]',
        variant === 'drawer' ? 'm-0 ms-auto h-dvh max-h-dvh w-full max-w-md'
          : variant === 'sheet' ? cx('m-0 mt-auto w-full max-w-none sm:m-auto sm:w-[calc(100%-2rem)]', size === 'sm' ? 'sm:max-w-sm' : size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-lg')
          : cx('m-auto w-[calc(100%-2rem)]', width),
      )}
    >
      <div className={cx(
        // Each variant sets its own height cap; a shared max-h here would override it and make
        // the whole dialog scroll (header included) instead of just the body.
        'flex flex-col border border-border bg-surface shadow-[var(--shadow-lg)]',
        variant === 'drawer' ? 'drawer-end-in h-full max-h-full rounded-s-2xl'
          : variant === 'sheet' ? 'sheet-up-in max-h-[92dvh] rounded-t-3xl sm:max-h-[85dvh] sm:rounded-2xl'
          : 'dialog-in max-h-[85dvh] rounded-2xl',
      )}>
        <header className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-semibold">{title}</h2>
            {description ? <p className="mt-0.5 text-sm text-text-muted">{description}</p> : null}
          </div>
          <button type="button" onClick={onClose} aria-label="بستن" autoFocus className="flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text">
            <X className="size-4" aria-hidden="true" />
          </button>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
        {footer ? <footer className="flex justify-end gap-2 border-t border-border px-5 py-3">{footer}</footer> : null}
      </div>
    </dialog>
  );
}
