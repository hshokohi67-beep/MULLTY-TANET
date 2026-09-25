'use client';

import { useEffect, useRef } from 'react';
import { ChevronDown, Download, FileSpreadsheet, FileText, Printer } from 'lucide-react';

const REPORTS = [
  ['summary', 'خلاصه و مقایسه'],
  ['daily', 'روزبه‌روز'],
  ['products', 'محصولات'],
  ['branches', 'شعبه‌ها'],
] as const;

/** Excel / CSV downloads (proxied through the web server) and the printable PDF view. */
export function ExportMenu({ query, printHref }: { query: string; printHref: string }) {
  const ref = useRef<HTMLDetailsElement>(null);

  // Close when clicking elsewhere.
  useEffect(() => {
    const close = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) ref.current.open = false; };
    document.addEventListener('click', close);

    return () => document.removeEventListener('click', close);
  }, []);

  const link = (report: string, format: string) => `/dashboard/reports/export?${query}${query ? '&' : ''}report=${report}&format=${format}`;
  const item = 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted';

  return (
    <details ref={ref} className="group relative">
      <summary className="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-lg border border-border bg-surface px-3.5 text-sm font-medium hover:bg-surface-muted [&::-webkit-details-marker]:hidden">
        <Download className="size-4" aria-hidden="true" />خروجی<ChevronDown className="size-4 text-text-subtle transition-transform group-open:rotate-180" aria-hidden="true" />
      </summary>
      <div className="dialog-in absolute end-0 top-full z-40 mt-2 w-64 rounded-xl border border-border bg-surface-raised p-1.5 shadow-[var(--shadow-lg)]">
        <p className="px-3 pb-1 pt-1.5 text-[11px] font-semibold text-text-subtle">اکسل</p>
        {REPORTS.map(([key, label]) => (
          <a key={key} href={link(key, 'xlsx')} className={item} download><FileSpreadsheet className="size-4 text-success" aria-hidden="true" />{label}</a>
        ))}
        <p className="border-t border-border px-3 pb-1 pt-2 text-[11px] font-semibold text-text-subtle">CSV</p>
        {REPORTS.map(([key, label]) => (
          <a key={key} href={link(key, 'csv')} className={item} download><FileText className="size-4 text-text-subtle" aria-hidden="true" />{label}</a>
        ))}
        <a href={printHref} target="_blank" rel="noopener" className={`${item} mt-1 border-t border-border pt-2.5`}>
          <Printer className="size-4 text-brand" aria-hidden="true" />نسخه‌ی چاپی / PDF
        </a>
      </div>
    </details>
  );
}
