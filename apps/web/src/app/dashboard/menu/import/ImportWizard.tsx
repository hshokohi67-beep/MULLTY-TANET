'use client';

import Link from 'next/link';
import { useRef, useState, useTransition } from 'react';
import { CircleAlert, CircleCheck, Download, FileSpreadsheet, FolderPlus, RefreshCw, Upload } from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { previewImport, runImport, type ImportField, type ImportPlan, type ImportResult, type ImportRow } from '@/app/actions/catalog-import';

const FIELD_LABELS: Record<ImportField, string> = { name: 'نام', category: 'دسته', price: 'قیمت', description: 'توضیح', calories: 'کالری', type: 'نوع (ووکامرس)' };
const STATUS: Record<ImportRow['status'], { label: string; tone: 'success' | 'info' | 'neutral' | 'danger' | 'warning' }> = {
  create: { label: 'جدید', tone: 'success' },
  update: { label: 'به‌روزرسانی', tone: 'info' },
  same: { label: 'بدون تغییر', tone: 'neutral' },
  error: { label: 'ایراد', tone: 'danger' },
  skip: { label: 'رد شد', tone: 'warning' },
};
type Filter = 'all' | 'create' | 'update' | 'problems';

/**
 * Upload → preview (what would be created, what changes, what's wrong, which column is which)
 * → import. Nothing changes until «ورود به منو»; the same file can be uploaded again later to
 * update prices.
 */
export function ImportWizard() {
  const [file, setFile] = useState<File | null>(null);
  const [unit, setUnit] = useState<'toman' | 'rial'>('toman');
  const [columns, setColumns] = useState<Partial<Record<ImportField, number>> | null>(null);
  const [plan, setPlan] = useState<ImportPlan | null>(null);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<Filter>('all');
  const [pending, start] = useTransition();
  const input = useRef<HTMLInputElement>(null);

  const form = (cols: Partial<Record<ImportField, number>> | null) => {
    const fd = new FormData();
    if (file) fd.append('file', file);
    fd.append('unit', unit);
    for (const [k, v] of Object.entries(cols ?? {})) if (v !== undefined && v !== null) fd.append(`columns[${k}]`, String(v));

    return fd;
  };

  const check = (cols: Partial<Record<ImportField, number>> | null = columns) => start(async () => {
    setError(null);
    setResult(null);
    const r = await previewImport(form(cols));
    if (!r.ok) { setError(r.message); setPlan(null); return; }
    setPlan(r.plan);
    setColumns(r.plan.columns);
  });

  const apply = () => start(async () => {
    setError(null);
    const r = await runImport(form(columns));
    if (!r.ok) { setError(r.message); return; }
    setResult(r.result);
    setPlan(null);
  });

  const reset = () => { setFile(null); setPlan(null); setColumns(null); setResult(null); setError(null); if (input.current) input.current.value = ''; };

  if (result) {
    return (
      <Card className="flex flex-col items-center gap-4 p-8 text-center">
        <span className="flex size-14 items-center justify-center rounded-2xl bg-success-soft text-success"><CircleCheck className="size-7" aria-hidden="true" /></span>
        <h2 className="text-xl font-bold">منو به‌روز شد</h2>
        <p className="text-text-muted">
          {formatNumber(result.created)} محصول تازه، {formatNumber(result.updated)} محصول به‌روزرسانی و {formatNumber(result.categories)} دسته‌ی تازه.
        </p>
        {result.not_created ? <Alert tone="warning">{formatNumber(result.not_created)} محصول به‌خاطر سقف تعداد محصول در پلن فعلی وارد نشد.</Alert> : null}
        <div className="flex flex-wrap justify-center gap-2">
          <Link href="/dashboard/menu" className="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-strong">دیدن منو</Link>
          <Button variant="secondary" onClick={reset}>فایل دیگر</Button>
        </div>
      </Card>
    );
  }

  const shown = (plan?.rows ?? []).filter((r) => filter === 'all' || (filter === 'problems' ? r.problems.length > 0 || r.status === 'error' || r.status === 'skip' : r.status === filter));
  const doable = plan ? plan.summary.create + plan.summary.update : 0;

  return (
    <div className="flex flex-col gap-5">
      <Card className="flex flex-col gap-5 p-5">
        <CardHeader icon={<FileSpreadsheet className="size-5" />} title="فایل منو"
          description="فایل اکسل (xlsx) یا CSV، مثل خروجی محصولات ووکامرس. ستون‌های نام، دسته و قیمت کافی است؛ توضیح و کالری اختیاری‌اند." />
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
          <label className={cx('flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed px-4 py-5 transition-colors', file ? 'border-brand bg-brand-soft/40' : 'border-border-strong hover:border-brand')}>
            <Upload className="size-5 shrink-0 text-brand" aria-hidden="true" />
            <span className="min-w-0 flex-1 text-sm">{file ? <span className="font-semibold">{file.name}</span> : 'انتخاب فایل (xlsx یا csv، حداکثر ۲ مگابایت)'}</span>
            <input ref={input} type="file" accept=".xlsx,.csv" className="sr-only"
              onChange={(e) => { setFile(e.target.files?.[0] ?? null); setPlan(null); setColumns(null); setError(null); }} />
          </label>
          <fieldset className="flex gap-1 rounded-xl bg-surface-muted p-1 text-sm">
            <legend className="sr-only">واحد قیمت‌ها</legend>
            {(['toman', 'rial'] as const).map((u) => (
              <button key={u} type="button" aria-pressed={unit === u} onClick={() => setUnit(u)}
                className={cx('h-9 rounded-lg px-3', unit === u ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>
                قیمت‌ها به {u === 'toman' ? 'تومان' : 'ریال'}
              </button>
            ))}
          </fieldset>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button onClick={() => check(null)} disabled={!file} loading={pending && !plan} icon={<RefreshCw className="size-4" />}>بررسی فایل</Button>
          {/* A file download (route handler), not a page: a plain link with `download`. */}
          <a href="/dashboard/menu/import/template" download className="inline-flex h-10 items-center gap-1.5 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted">
            <Download className="size-4" aria-hidden="true" />دانلود فایل نمونه
          </a>
          <span className="text-xs text-text-subtle">تا «ورود به منو» را نزنید چیزی تغییر نمی‌کند.</span>
        </div>
      </Card>

      {error ? <Alert tone="danger">{error}</Alert> : null}

      {plan ? (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            {([['create', 'محصول جدید'], ['update', 'به‌روزرسانی'], ['same', 'بدون تغییر'], ['error', 'ایراددار'], ['skip', 'رد شده']] as const).map(([k, label]) => (
              <div key={k} className="rounded-2xl border border-border bg-surface p-4">
                <p className="text-xs text-text-muted">{label}</p>
                <p className={cx('mt-1 text-2xl font-black tabular', k === 'error' && plan.summary.error ? 'text-danger' : '')}>{formatNumber(plan.summary[k])}</p>
              </div>
            ))}
          </div>

          <Card className="flex flex-col gap-4 p-5">
            <CardHeader title="کدام ستون چیست؟" description="از روی عنوان ستون‌ها تشخیص داده شد؛ اگر درست نیست عوض کنید و دوباره بررسی کنید." />
            <div className="grid gap-3 sm:grid-cols-3">
              {(Object.keys(FIELD_LABELS) as ImportField[]).map((f) => (
                <label key={f} className="flex flex-col gap-1 text-sm">
                  <span className="font-medium">{FIELD_LABELS[f]}{f === 'name' || f === 'price' ? <span className="text-danger"> *</span> : null}</span>
                  <select value={columns?.[f] ?? ''} onChange={(e) => setColumns({ ...(columns ?? {}), [f]: e.target.value === '' ? undefined : Number(e.target.value) })}
                    className="h-10 rounded-xl border border-border-strong bg-surface px-2">
                    <option value="">— ندارد —</option>
                    {plan.header.map((h, i) => <option key={i} value={i}>{h || `ستون ${formatNumber(i + 1)}`}</option>)}
                  </select>
                </label>
              ))}
            </div>
            <div><Button variant="secondary" size="sm" onClick={() => check(columns)} loading={pending} icon={<RefreshCw className="size-4" />}>بررسی دوباره با این ستون‌ها</Button></div>
          </Card>

          {plan.new_categories.length ? (
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <FolderPlus className="size-4 text-brand" aria-hidden="true" /><span className="text-text-muted">دسته‌های تازه:</span>
              {plan.new_categories.map((c) => <span key={c} className="rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-semibold">{c}</span>)}
            </div>
          ) : null}

          <Card className="overflow-hidden">
            <nav role="tablist" aria-label="ردیف‌ها" className="flex gap-1 overflow-x-auto border-b border-border p-2 text-sm">
              {([['all', 'همه'], ['create', 'جدید'], ['update', 'تغییر'], ['problems', 'ایراد و هشدار']] as const).map(([k, label]) => (
                <button key={k} role="tab" type="button" aria-selected={filter === k} onClick={() => setFilter(k)}
                  className={cx('rounded-lg px-3 py-1.5', filter === k ? 'bg-surface-muted font-semibold' : 'text-text-muted hover:text-text')}>{label}</button>
              ))}
            </nav>
            {shown.length ? (
              <ul className="max-h-[32rem] divide-y divide-border overflow-y-auto">
                {shown.map((r) => (
                  <li key={r.line} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                    <span className="w-14 shrink-0 whitespace-nowrap text-xs text-text-subtle tabular">ردیف {formatNumber(r.line)}</span>
                    <span className="min-w-0 flex-1 font-semibold">{r.name}</span>
                    {r.category.length ? <span className="text-xs text-text-muted">{r.category.join(' ← ')}</span> : null}
                    <span className="tabular text-xs">
                      {r.changes?.price ? <>{formatMoney(r.changes.price.from ?? 0)} ← <b>{formatMoney(r.changes.price.to)}</b></> : r.price ? formatMoney(r.price) : '—'}
                    </span>
                    <Badge tone={STATUS[r.status].tone}>{STATUS[r.status].label}</Badge>
                    {r.problems.length ? (
                      <p className="flex w-full items-start gap-1.5 ps-[4.25rem] text-xs text-warning"><CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />{r.problems.join(' ')}</p>
                    ) : null}
                  </li>
                ))}
              </ul>
            ) : <p className="px-4 py-8 text-center text-sm text-text-muted">ردیفی در این دسته نیست.</p>}
          </Card>

          <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface p-3 shadow-[var(--shadow-lg)]">
            <p className="text-sm text-text-muted">
              {doable ? `${formatNumber(plan.summary.create)} محصول تازه و ${formatNumber(plan.summary.update)} به‌روزرسانی آماده‌ی ورود است.` : 'چیزی برای ورود نیست.'}
              {plan.summary.error ? ` ${formatNumber(plan.summary.error)} ردیف ایراددار وارد نمی‌شود.` : ''}
            </p>
            <Button onClick={apply} loading={pending} disabled={!doable}>ورود به منو</Button>
          </div>
        </>
      ) : null}
    </div>
  );
}
