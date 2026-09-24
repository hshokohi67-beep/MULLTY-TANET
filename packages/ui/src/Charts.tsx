'use client';

import { useId, useState, type ReactNode } from 'react';
import { cx } from './cx.ts';

/*
 * Small, dependency-free SVG charts following the dataviz method:
 * thin marks, one axis, recessive grid, hover/focus tooltips, a legend for 2+ series,
 * and a table view so no value is gated behind hover. Charts flow right-to-left
 * (the first category sits at the right edge), like the page they live in.
 * Colours come from the --viz-* tokens, validated for light and dark surfaces.
 */

type Format = (value: number) => string;

/* ---------------------------------- Sparkline ---------------------------------- */

/** A 2px trend line with a soft wash and an end dot. Decorative: the tile states the numbers. */
export function Sparkline({ values, className }: { values: number[]; className?: string }) {
  const id = useId();
  if (values.length < 2) return null;

  const w = 120;
  const h = 32;
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const span = max - min || 1;
  // RTL: index 0 at the right edge.
  const pts = values.map((v, i) => [w - (i / (values.length - 1)) * (w - 8) - 4, h - 4 - ((v - min) / span) * (h - 8)] as const);
  const line = pts.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const last = pts[pts.length - 1];

  return (
    <svg viewBox={`0 0 ${w} ${h}`} className={cx('h-8 w-full', className)} aria-hidden="true" preserveAspectRatio="none">
      <defs>
        <linearGradient id={`${id}-wash`} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor="var(--viz-1)" stopOpacity="0.14" />
          <stop offset="100%" stopColor="var(--viz-1)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={`${line} L${last[0]},${h} L${pts[0][0]},${h} Z`} fill={`url(#${id}-wash)`} />
      <path d={line} fill="none" stroke="var(--viz-1)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
      <circle cx={last[0]} cy={last[1]} r="3.5" fill="var(--viz-1)" stroke="var(--color-surface)" strokeWidth="2" />
    </svg>
  );
}

/* ------------------------------- Columns vs reference ------------------------------- */

export interface ColumnDatum {
  label: string;      // x category, e.g. "۱۴"
  value: number;      // this period (brand columns)
  reference?: number; // typical / previous period (neutral step line)
}

function niceMax(max: number): number {
  if (max <= 0) return 1;
  const pow = 10 ** Math.floor(Math.log10(max));
  const step = [1, 2, 2.5, 5, 10].find((s) => s * pow >= max / 3) ?? 10;

  return Math.ceil(max / (step * pow)) * step * pow;
}

/**
 * Columns for one series plus an optional neutral reference (a step line), e.g. "sales per hour
 * today vs a typical day". Hover or focus a column for both values; the table view lists all.
 */
export function ColumnChart({ data, format, valueLabel, referenceLabel, height = 200, caption }: {
  data: ColumnDatum[];
  format: Format;
  valueLabel: string;
  referenceLabel?: string;
  height?: number;
  caption: string;
}) {
  const [active, setActive] = useState<number | null>(null);
  const [table, setTable] = useState(false);
  const hasRef = data.some((d) => d.reference !== undefined);
  const w = 640;
  const h = height;
  const padTop = 12;
  const padBottom = 24;
  const padStart = 56; // y-axis labels live on the right (start) edge in RTL
  const plotW = w - padStart - 8;
  const plotH = h - padTop - padBottom;
  const top = niceMax(Math.max(...data.map((d) => Math.max(d.value, d.reference ?? 0)), 0));
  const slot = plotW / Math.max(data.length, 1);
  const barW = Math.min(24, slot - 4);
  const y = (v: number) => padTop + plotH - (v / top) * plotH;
  // RTL: category 0 is next to the axis on the right.
  const xCenter = (i: number) => w - padStart - slot * i - slot / 2;
  const ticks = [0, top / 2, top];
  const labelEvery = data.length > 12 ? 3 : 1;

  const refPath = hasRef
    ? data.map((d, i) => {
      const x0 = xCenter(i) + slot / 2;
      const x1 = xCenter(i) - slot / 2;
      const yy = y(d.reference ?? 0);
      return `${i === 0 ? 'M' : 'L'}${x0.toFixed(1)},${yy.toFixed(1)} L${x1.toFixed(1)},${yy.toFixed(1)}`;
    }).join(' ')
    : '';

  const hovered = active === null ? null : data[active];

  return (
    <figure className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
        <figcaption className="sr-only">{caption}</figcaption>
        {hasRef ? (
          <div className="flex items-center gap-4" aria-hidden="true">
            <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-viz-1" />{valueLabel}</span>
            <span className="flex items-center gap-1.5"><span className="h-0.5 w-4 rounded bg-viz-ref" />{referenceLabel}</span>
          </div>
        ) : <span />}
        <button type="button" onClick={() => setTable((t) => !t)} className="rounded px-1.5 py-0.5 text-text-muted underline-offset-4 hover:text-text hover:underline">
          {table ? 'نمایش نمودار' : 'نمایش جدول'}
        </button>
      </div>

      {table ? (
        <div className="max-h-72 overflow-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-surface-muted text-xs text-text-muted">
              <tr><th className="px-3 py-2 text-start font-medium">ساعت</th><th className="px-3 py-2 text-start font-medium">{valueLabel}</th>{hasRef ? <th className="px-3 py-2 text-start font-medium">{referenceLabel}</th> : null}</tr>
            </thead>
            <tbody className="tabular divide-y divide-border">
              {data.map((d) => (
                <tr key={d.label}><td className="px-3 py-1.5">{d.label}</td><td className="px-3 py-1.5">{format(d.value)}</td>{hasRef ? <td className="px-3 py-1.5 text-text-muted">{format(d.reference ?? 0)}</td> : null}</tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="relative">
          <svg viewBox={`0 0 ${w} ${h}`} className="h-auto w-full" role="img" aria-label={caption} onPointerLeave={() => setActive(null)}>
            {ticks.map((t) => (
              <g key={t}>
                <line x1={8} x2={w - padStart} y1={y(t)} y2={y(t)} stroke="var(--viz-grid)" strokeWidth="1" />
                <text x={w - padStart + 8} y={y(t)} dy="0.35em" textAnchor="start" className="fill-[var(--color-text-subtle)] text-[11px]">{format(t)}</text>
              </g>
            ))}
            {data.map((d, i) => {
              const x = xCenter(i);
              const bh = Math.max(d.value > 0 ? 2 : 0, (d.value / top) * plotH);
              const r = Math.min(4, bh / 2);
              const by = padTop + plotH - bh;
              const path = `M${x - barW / 2},${padTop + plotH} V${by + r} Q${x - barW / 2},${by} ${x - barW / 2 + r},${by} H${x + barW / 2 - r} Q${x + barW / 2},${by} ${x + barW / 2},${by + r} V${padTop + plotH} Z`;

              return (
                <g key={d.label}>
                  {bh > 0 ? <path d={path} fill="var(--viz-1)" opacity={active === null || active === i ? 1 : 0.55} className="transition-opacity duration-[var(--duration-fast)]" /> : null}
                  {i % labelEvery === 0 ? (
                    <text x={x} y={h - 6} textAnchor="middle" className="fill-[var(--color-text-subtle)] text-[11px]">{d.label}</text>
                  ) : null}
                  {/* The hit target is the whole slot, taller and wider than the bar. */}
                  <rect
                    x={x - slot / 2} y={padTop} width={slot} height={plotH} fill="transparent"
                    tabIndex={0} role="button" aria-label={`${d.label}: ${format(d.value)}${hasRef ? `، ${referenceLabel} ${format(d.reference ?? 0)}` : ''}`}
                    onPointerEnter={() => setActive(i)} onFocus={() => setActive(i)} onBlur={() => setActive(null)}
                    className="cursor-default outline-none"
                  />
                </g>
              );
            })}
            {hasRef ? <path d={refPath} fill="none" stroke="var(--viz-ref)" strokeWidth="2" strokeLinejoin="round" pointerEvents="none" /> : null}
            <line x1={8} x2={w - padStart} y1={padTop + plotH} y2={padTop + plotH} stroke="var(--color-border-strong)" strokeWidth="1" />
          </svg>
          {hovered && active !== null ? (
            <div
              className="pointer-events-none absolute top-0 z-10 min-w-36 -translate-x-1/2 rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs shadow-[var(--shadow-md)]"
              style={{ left: `${(xCenter(active) / w) * 100}%` }}
              role="status"
            >
              <p className="text-text-muted">ساعت {hovered.label}</p>
              <p className="mt-1 flex items-center gap-2"><span className="h-0.5 w-3 rounded bg-viz-1" /><strong className="text-sm text-text">{format(hovered.value)}</strong><span className="text-text-muted">{valueLabel}</span></p>
              {hasRef ? <p className="flex items-center gap-2"><span className="h-0.5 w-3 rounded bg-viz-ref" /><span className="text-text">{format(hovered.reference ?? 0)}</span><span className="text-text-muted">{referenceLabel}</span></p> : null}
            </div>
          ) : null}
        </div>
      )}
    </figure>
  );
}

/* ---------------------------------- Share bar ---------------------------------- */

const CAT = ['var(--viz-cat-1)', 'var(--viz-cat-2)', 'var(--viz-cat-3)', 'var(--viz-cat-4)'];

/**
 * Parts of a whole as one horizontal bar (2px surface gaps), with a legend that always shows
 * the value and share as text: several slots are below 3:1 on the light surface, so labels
 * carry the meaning, never colour alone. Up to 4 parts; more fold into «سایر».
 */
export function ShareBar({ parts, format, caption, empty }: { parts: { key: string; label: string; value: number }[]; format: Format; caption: string; empty?: ReactNode }) {
  const [active, setActive] = useState<string | null>(null);
  const shown = parts.filter((p) => p.value > 0);
  const total = shown.reduce((s, p) => s + p.value, 0);

  if (total <= 0) return <div className="py-6 text-center text-sm text-text-muted">{empty ?? 'هنوز داده‌ای نیست.'}</div>;

  const pct = (v: number) => new Intl.NumberFormat('fa-IR', { style: 'percent', maximumFractionDigits: 0 }).format(v / total);

  return (
    <figure className="flex flex-col gap-4">
      <figcaption className="sr-only">{caption}</figcaption>
      <div className="flex h-3 w-full gap-0.5 overflow-hidden rounded-full" role="img" aria-label={caption}>
        {shown.map((p, i) => (
          <div
            key={p.key}
            style={{ width: `${(p.value / total) * 100}%`, background: CAT[i % CAT.length] }}
            className={cx('h-full transition-opacity duration-[var(--duration-fast)]', active && active !== p.key && 'opacity-40')}
            onPointerEnter={() => setActive(p.key)}
            onPointerLeave={() => setActive(null)}
            title={`${p.label}: ${format(p.value)} (${pct(p.value)})`}
          />
        ))}
      </div>
      <ul className="grid gap-1">
        {shown.map((p, i) => (
          <li
            key={p.key}
            className={cx('flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm transition-colors', active === p.key && 'bg-surface-muted')}
            onPointerEnter={() => setActive(p.key)}
            onPointerLeave={() => setActive(null)}
          >
            <span className="flex items-center gap-2 text-text-muted"><span className="size-2.5 rounded-sm" style={{ background: CAT[i % CAT.length] }} aria-hidden="true" />{p.label}</span>
            <span className="tabular text-text"><strong className="font-semibold">{format(p.value)}</strong> <span className="text-xs text-text-muted">{pct(p.value)}</span></span>
          </li>
        ))}
      </ul>
    </figure>
  );
}

/* ---------------------------------- Stat tile ---------------------------------- */

export interface Delta {
  /** ratio change, e.g. 0.12 = +12% */
  ratio: number | null;
  /** "vs yesterday at this time" */
  against: string;
  /** whether up is good (sales: yes; cancellations: no) */
  upIsGood?: boolean;
}

/**
 * label • value • optional delta vs a named period • optional trend. The delta carries direction
 * with an arrow + sign + words, so it never relies on green/red alone.
 */
export function StatTile({ label, value, delta, trend, icon, hint }: { label: string; value: ReactNode; delta?: Delta; trend?: number[]; icon?: ReactNode; hint?: ReactNode }) {
  const r = delta?.ratio ?? null;
  const up = r !== null && r > 0.005;
  const down = r !== null && r < -0.005;
  const good = r === null ? null : (up && (delta?.upIsGood ?? true)) || (down && !(delta?.upIsGood ?? true));
  const pct = r === null ? null : new Intl.NumberFormat('fa-IR', { style: 'percent', maximumFractionDigits: 0, signDisplay: 'exceptZero' }).format(r);

  return (
    <div className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm text-text-muted">{label}</p>
        {icon ? <span className="flex size-8 items-center justify-center rounded-lg bg-surface-muted text-text-muted [&>svg]:size-4" aria-hidden="true">{icon}</span> : null}
      </div>
      <p className="text-2xl font-bold leading-tight text-text">{value}</p>
      {delta ? (
        <p className="flex flex-wrap items-center gap-1.5 text-xs">
          {pct === null ? (
            <span className="text-text-subtle">بدون داده‌ی مقایسه</span>
          ) : (
            <>
              <span className={cx('inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold', !up && !down ? 'bg-surface-muted text-text-muted' : good ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger')}>
                <span aria-hidden="true">{up ? '▲' : down ? '▼' : '●'}</span>{pct}
              </span>
              <span className="text-text-subtle">{delta.against}</span>
            </>
          )}
        </p>
      ) : hint ? <p className="text-xs text-text-subtle">{hint}</p> : null}
      {trend && trend.length > 1 ? <Sparkline values={trend} className="mt-1" /> : null}
    </div>
  );
}

/* ---------------------------------- Heatmap ---------------------------------- */

export interface HeatCell { row: number; col: number; value: number }

/**
 * Rows × columns of one measure on a single-hue ramp (brand, light → dark), e.g. weekday × hour.
 * Five steps plus an "empty" state; a legend explains the ramp; hover/focus shows the value; the
 * table view lists every non-empty cell. Columns flow right-to-left.
 */
export function Heatmap({ rows, cols, cells, format, caption, colLabel }: {
  rows: string[];
  cols: { key: number; label: string }[];
  cells: HeatCell[];
  format: Format;
  caption: string;
  colLabel: (col: number) => string;
}) {
  const [active, setActive] = useState<{ row: number; col: number } | null>(null);
  const [table, setTable] = useState(false);
  const max = Math.max(0, ...cells.map((c) => c.value));
  const lookup = new Map(cells.map((c) => [`${c.row}:${c.col}`, c.value]));
  const STEPS = [18, 34, 52, 72, 100];
  const step = (v: number) => (v <= 0 || max <= 0 ? -1 : Math.min(STEPS.length - 1, Math.floor((v / max) * STEPS.length - 1e-9)));
  const fill = (v: number) => {
    const s = step(v);
    return s < 0 ? 'var(--color-surface-muted)' : `color-mix(in oklab, var(--viz-1) ${STEPS[s]}%, var(--color-surface))`;
  };
  const current = active ? lookup.get(`${active.row}:${active.col}`) ?? 0 : null;

  return (
    <figure className="flex flex-col gap-3">
      <figcaption className="sr-only">{caption}</figcaption>
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
        <div className="flex items-center gap-1.5" aria-hidden="true">
          <span>کم</span>
          {STEPS.map((p) => <span key={p} className="size-3 rounded-sm" style={{ background: `color-mix(in oklab, var(--viz-1) ${p}%, var(--color-surface))` }} />)}
          <span>زیاد</span>
        </div>
        <div className="flex items-center gap-3">
          {active && current !== null ? <span className="text-text"><strong>{format(current)}</strong> <span className="text-text-muted">{rows[active.row]}، {colLabel(active.col)}</span></span> : null}
          <button type="button" onClick={() => setTable((t) => !t)} className="rounded px-1.5 py-0.5 underline-offset-4 hover:text-text hover:underline">{table ? 'نمایش نمودار' : 'نمایش جدول'}</button>
        </div>
      </div>
      {table ? (
        <div className="max-h-72 overflow-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-surface-muted text-xs text-text-muted"><tr><th className="px-3 py-2 text-start font-medium">روز</th><th className="px-3 py-2 text-start font-medium">ساعت</th><th className="px-3 py-2 text-start font-medium">مقدار</th></tr></thead>
            <tbody className="tabular divide-y divide-border">
              {[...cells].sort((a, b) => b.value - a.value).map((c) => (
                <tr key={`${c.row}:${c.col}`}><td className="px-3 py-1.5">{rows[c.row]}</td><td className="px-3 py-1.5">{colLabel(c.col)}</td><td className="px-3 py-1.5">{format(c.value)}</td></tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="overflow-x-auto" onPointerLeave={() => setActive(null)}>
          <div className="grid min-w-[36rem] gap-[3px]" style={{ gridTemplateColumns: `4.5rem repeat(${cols.length}, minmax(0, 1fr))` }}>
            <span />
            {cols.map((c) => <span key={c.key} className="pb-1 text-center text-[10px] text-text-subtle">{c.label}</span>)}
            {rows.map((row, r) => (
              <div key={row} className="contents">
                <span className="flex items-center text-xs text-text-muted">{row}</span>
                {cols.map((c) => {
                  const v = lookup.get(`${r}:${c.key}`) ?? 0;
                  const on = active?.row === r && active.col === c.key;

                  return (
                    <span
                      key={c.key}
                      tabIndex={0}
                      role="img"
                      aria-label={`${row}، ${colLabel(c.key)}: ${format(v)}`}
                      onPointerEnter={() => setActive({ row: r, col: c.key })}
                      onFocus={() => setActive({ row: r, col: c.key })}
                      className={cx('h-7 rounded-[4px] outline-none transition-shadow', on && 'shadow-[0_0_0_2px_var(--color-text)]')}
                      style={{ background: fill(v) }}
                    />
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      )}
    </figure>
  );
}
