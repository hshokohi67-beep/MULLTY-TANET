'use client';

import { ColumnChart, Heatmap, ShareBar, type ColumnDatum, type HeatCell } from '@cafe/ui';
import { formatMoneyCompact, toPersianDigits } from '@cafe/locale';

/**
 * The shared charts with money formatting applied on the client (server components can't pass
 * formatter functions across the boundary).
 */
export function MoneyColumnChart(props: { data: ColumnDatum[]; valueLabel: string; referenceLabel?: string; caption: string; height?: number }) {
  return <ColumnChart {...props} format={(v) => formatMoneyCompact(v, { withUnit: false })} />;
}

export function MoneyShareBar(props: { parts: { key: string; label: string; value: number }[]; caption: string; empty?: string }) {
  return <ShareBar {...props} format={(v) => formatMoneyCompact(v)} />;
}

export function MoneyHeatmap(props: { rows: string[]; cols: { key: number; label: string }[]; cells: HeatCell[]; caption: string }) {
  return <Heatmap {...props} format={(v) => formatMoneyCompact(v)} colLabel={(c) => `ساعت ${toPersianDigits(c)}`} />;
}
