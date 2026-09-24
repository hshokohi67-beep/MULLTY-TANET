import type { HTMLAttributes, ReactNode } from 'react';
import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import { cx } from './cx.ts';

export function Card({ className, ...rest }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cx('rounded-xl border border-border bg-surface shadow-[var(--shadow-sm)]', className)} {...rest} />;
}

export function CardHeader({ title, description, actions, icon }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; icon?: ReactNode }) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
      <div className="flex min-w-0 items-start gap-3">
        {icon ? <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand [&>svg]:size-4" aria-hidden="true">{icon}</span> : null}
        <div className="min-w-0">
          <h2 className="text-base font-semibold text-text">{title}</h2>
          {description ? <p className="mt-0.5 text-sm text-text-muted">{description}</p> : null}
        </div>
      </div>
      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export type Tone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'info' | 'accent';

const tones: Record<Tone, string> = {
  neutral: 'bg-surface-muted text-text-muted',
  brand: 'bg-brand-soft text-brand-strong',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-warning',
  danger: 'bg-danger-soft text-danger',
  info: 'bg-info-soft text-info',
  accent: 'bg-accent-soft text-accent',
};

const dots: Record<Tone, string> = {
  neutral: 'bg-text-subtle', brand: 'bg-brand', success: 'bg-success', warning: 'bg-warning', danger: 'bg-danger', info: 'bg-info', accent: 'bg-accent',
};

export function Badge({ tone = 'neutral', dot = false, children }: { tone?: Tone; dot?: boolean; children: ReactNode }) {
  return (
    <span className={cx('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', tones[tone])}>
      {dot ? <span className={cx('size-1.5 rounded-full', dots[tone])} aria-hidden="true" /> : null}
      {children}
    </span>
  );
}

const alertIcons = { info: Info, success: CircleCheck, warning: TriangleAlert, danger: CircleAlert } as const;

export function Alert({ tone = 'info', title, children, action }: { tone?: 'info' | 'success' | 'warning' | 'danger'; title?: ReactNode; children?: ReactNode; action?: ReactNode }) {
  const Icon = alertIcons[tone];

  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cx('flex flex-wrap items-start justify-between gap-3 rounded-lg px-4 py-3 text-sm', tones[tone])}>
      <div className="flex min-w-0 items-start gap-2.5">
        <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <div className="min-w-0">
          {title ? <p className="font-semibold">{title}</p> : null}
          {children ? <div className={title ? 'mt-0.5' : undefined}>{children}</div> : null}
        </div>
      </div>
      {action}
    </div>
  );
}

/** Empty states explain what's missing and offer the next action. They are never just "no data". */
export function EmptyState({ title, description, action, icon }: { title: string; description?: string; action?: ReactNode; icon?: ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
      {icon ? (
        <div className="flex size-14 items-center justify-center rounded-2xl bg-surface-muted text-text-subtle [&>svg]:size-7" aria-hidden="true">{icon}</div>
      ) : null}
      <p className="text-base font-semibold text-text">{title}</p>
      {description ? <p className="max-w-sm text-sm text-text-muted">{description}</p> : null}
      {action}
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cx('skeleton h-4', className)} aria-hidden="true" />;
}

/** Island for inherently left-to-right values (domains, tokens, SKUs, emails, latin codes). */
export function Ltr({ children, className }: { children: ReactNode; className?: string }) {
  return <bdi dir="ltr" className={cx('font-mono text-[0.92em]', className)}>{children}</bdi>;
}
