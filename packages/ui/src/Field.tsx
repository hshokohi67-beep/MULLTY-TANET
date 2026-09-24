'use client';

import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cx } from './cx.ts';

const control =
  'w-full rounded-md border bg-surface px-3 text-sm text-text placeholder:text-text-subtle ' +
  'transition-shadow duration-150 focus-visible:outline-none focus-visible:shadow-[var(--focus-ring)] focus-visible:border-brand ' +
  'disabled:bg-surface-muted disabled:cursor-not-allowed';

interface FieldShellProps {
  label: string;
  hint?: ReactNode;
  error?: string;
  required?: boolean;
  children: (ids: { inputId: string; describedBy: string | undefined; invalid: boolean }) => ReactNode;
}

/** Label + control + hint + error, wired together for screen readers. */
export function FieldShell({ label, hint, error, required, children }: FieldShellProps) {
  const inputId = useId();
  const hintId = hint ? `${inputId}-hint` : undefined;
  const errorId = error ? `${inputId}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-text">
        {label}
        {required ? <span className="text-danger ms-0.5" aria-hidden="true">*</span> : null}
      </label>
      {children({ inputId, describedBy, invalid: Boolean(error) })}
      {hint ? <p id={hintId} className="text-xs text-text-muted">{hint}</p> : null}
      {error ? <p id={errorId} role="alert" className="text-xs text-danger">{error}</p> : null}
    </div>
  );
}

export interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string;
  hint?: ReactNode;
  error?: string;
  /** Technical values (email, domain, token, SKU, phone input) are entered left-to-right. */
  ltr?: boolean;
}

export function TextField({ label, hint, error, ltr, required, className, ...rest }: TextFieldProps) {
  return (
    <FieldShell label={label} hint={hint} error={error} required={required}>
      {({ inputId, describedBy, invalid }) => (
        <input
          id={inputId}
          required={required}
          aria-invalid={invalid || undefined}
          aria-describedby={describedBy}
          dir={ltr ? 'ltr' : undefined}
          className={cx(control, 'h-10', invalid ? 'border-danger' : 'border-border-strong', ltr && 'text-start', className)}
          {...rest}
        />
      )}
    </FieldShell>
  );
}

export interface TextAreaFieldProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'id'> {
  label: string;
  hint?: ReactNode;
  error?: string;
}

export function TextAreaField({ label, hint, error, required, className, ...rest }: TextAreaFieldProps) {
  return (
    <FieldShell label={label} hint={hint} error={error} required={required}>
      {({ inputId, describedBy, invalid }) => (
        <textarea
          id={inputId}
          required={required}
          aria-invalid={invalid || undefined}
          aria-describedby={describedBy}
          className={cx(control, 'min-h-24 py-2', invalid ? 'border-danger' : 'border-border-strong', className)}
          {...rest}
        />
      )}
    </FieldShell>
  );
}

export interface SelectFieldProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id'> {
  label: string;
  hint?: ReactNode;
  error?: string;
}

export function SelectField({ label, hint, error, required, className, children, ...rest }: SelectFieldProps) {
  return (
    <FieldShell label={label} hint={hint} error={error} required={required}>
      {({ inputId, describedBy, invalid }) => (
        <select
          id={inputId}
          required={required}
          aria-invalid={invalid || undefined}
          aria-describedby={describedBy}
          className={cx(control, 'h-10', invalid ? 'border-danger' : 'border-border-strong', className)}
          {...rest}
        >
          {children}
        </select>
      )}
    </FieldShell>
  );
}

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'id'> {
  label: string;
  hint?: string;
}

export function Checkbox({ label, hint, className, ...rest }: CheckboxProps) {
  const id = useId();

  return (
    <div className={cx('flex items-start gap-2', className)}>
      <input id={id} type="checkbox" className="mt-0.5 size-4 accent-[var(--color-brand)]" aria-describedby={hint ? `${id}-hint` : undefined} {...rest} />
      <div>
        <label htmlFor={id} className="text-sm text-text">{label}</label>
        {hint ? <p id={`${id}-hint`} className="text-xs text-text-muted">{hint}</p> : null}
      </div>
    </div>
  );
}
