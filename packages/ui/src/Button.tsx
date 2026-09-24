import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cx } from './cx.ts';

type Variant = 'primary' | 'secondary' | 'soft' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  icon?: ReactNode;
}

const variants: Record<Variant, string> = {
  primary: 'bg-brand text-on-brand shadow-[var(--shadow-sm)] hover:bg-brand-strong',
  secondary: 'bg-surface text-text border border-border-strong hover:bg-surface-muted',
  soft: 'bg-brand-soft text-brand-strong hover:bg-[color-mix(in_srgb,var(--color-brand-soft)_80%,var(--color-brand))]',
  ghost: 'bg-transparent text-text-muted hover:bg-surface-muted hover:text-text',
  danger: 'bg-danger text-white hover:opacity-90',
};

const sizes: Record<Size, string> = {
  sm: 'h-8 px-3 text-sm gap-1.5 [&_svg]:size-4',
  md: 'h-10 px-4 text-sm gap-2 [&_svg]:size-4',
  lg: 'h-12 px-5 text-base gap-2 [&_svg]:size-5',
};

export const buttonClass = (variant: Variant = 'primary', size: Size = 'md', className?: string) => cx(
  'inline-flex items-center justify-center rounded-lg font-medium whitespace-nowrap select-none',
  'transition-[background-color,color,box-shadow,transform] duration-[var(--duration-fast)] active:scale-[0.98]',
  'focus-visible:outline-none focus-visible:shadow-[var(--focus-ring)]',
  'disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100',
  variants[variant],
  sizes[size],
  className,
);

export function Button({ variant = 'primary', size = 'md', loading = false, icon, className, children, disabled, type = 'button', ...rest }: ButtonProps) {
  return (
    <button type={type} disabled={disabled || loading} aria-busy={loading || undefined} className={buttonClass(variant, size, className)} {...rest}>
      {loading ? <Spinner /> : icon}
      {children}
    </button>
  );
}

/** A square icon-only button; `label` is required because the icon alone says nothing to a screen reader. */
export function IconButton({ label, children, className, variant = 'ghost', size = 'md', ...rest }: Omit<ButtonProps, 'icon' | 'loading'> & { label: string }) {
  const box = size === 'sm' ? 'size-8 px-0' : size === 'lg' ? 'size-12 px-0' : 'size-10 px-0';

  return (
    <button type="button" aria-label={label} title={label} className={buttonClass(variant, size, cx(box, className))} {...rest}>
      {children}
    </button>
  );
}

export function Spinner({ label = 'در حال بارگذاری' }: { label?: string }) {
  return (
    <span role="status" className="inline-flex">
      <svg className="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.25" strokeWidth="3" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
      </svg>
      <span className="sr-only">{label}</span>
    </span>
  );
}
