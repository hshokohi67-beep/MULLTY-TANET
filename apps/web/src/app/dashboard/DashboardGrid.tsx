'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition, type DragEvent, type ReactNode } from 'react';
import { ArrowDown, ArrowUp, EyeOff, GripVertical, LayoutGrid, Plus, RotateCcw, Save, X } from 'lucide-react';
import { Alert, Button, cx, Dialog } from '@cafe/ui';
import { resetLayout, saveLayout, type LayoutWidget } from '@/app/actions/dashboard-layout';

export interface CatalogItem { key: string; title: string; description: string; sizes: LayoutWidget['size'][]; default_size: LayoutWidget['size'] }

const SPAN: Record<LayoutWidget['size'], string> = {
  sm: 'col-span-1',
  md: 'col-span-1 md:col-span-2',
  lg: 'col-span-1 md:col-span-2 xl:col-span-3',
};
const SIZE_LABEL: Record<LayoutWidget['size'], string> = { sm: 'کوچک', md: 'متوسط', lg: 'پهن' };

/**
 * The widget grid. In normal mode it only lays out server-rendered widgets. In edit mode the user
 * reorders (drag or arrows), resizes, hides and adds widgets; "save" stores the layout on the
 * server and refreshes, so newly added widgets load their data.
 */
export function DashboardGrid({ layout, catalog, nodes, isDefault }: {
  layout: LayoutWidget[];
  catalog: CatalogItem[];
  nodes: Record<string, ReactNode>;
  isDefault: boolean;
}) {
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<LayoutWidget[]>(layout);
  const [dragging, setDragging] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const byKey = new Map(catalog.map((c) => [c.key, c]));
  const shown = editing ? draft : layout;
  const hidden = catalog.filter((c) => !draft.some((w) => w.key === c.key));

  const move = (index: number, delta: number) => setDraft((d) => {
    const next = [...d];
    const target = index + delta;
    if (target < 0 || target >= next.length) return d;
    [next[index], next[target]] = [next[target], next[index]];
    return next;
  });

  const onDrop = (event: DragEvent, overKey: string) => {
    event.preventDefault();
    if (!dragging || dragging === overKey) return;
    setDraft((d) => {
      const from = d.findIndex((w) => w.key === dragging);
      const to = d.findIndex((w) => w.key === overKey);
      const next = [...d];
      const [item] = next.splice(from, 1);
      next.splice(to, 0, item);
      return next;
    });
    setDragging(null);
  };

  const save = () => start(async () => {
    const result = await saveLayout(draft);
    if (!result.ok) {
      setError(result.message ?? 'ذخیره نشد.');
      return;
    }
    setError(null);
    setEditing(false);
    router.refresh();
  });

  const reset = () => start(async () => {
    await resetLayout();
    setEditing(false);
    router.refresh();
  });

  return (
    <section aria-label="ویجت‌های پیشخوان" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-end gap-2">
        {editing ? (
          <>
            <p className="me-auto text-sm text-text-muted">ویجت‌ها را بکشید یا با فلش‌ها جابه‌جا کنید، اندازه را عوض کنید یا پنهان کنید.</p>
            <Button variant="secondary" size="sm" icon={<Plus />} onClick={() => setAdding(true)} disabled={hidden.length === 0}>افزودن ویجت</Button>
            {!isDefault ? <Button variant="ghost" size="sm" icon={<RotateCcw />} onClick={reset} loading={pending}>پیش‌فرض</Button> : null}
            <Button variant="ghost" size="sm" icon={<X />} onClick={() => { setDraft(layout); setEditing(false); }}>انصراف</Button>
            <Button size="sm" icon={<Save />} onClick={save} loading={pending}>ذخیره‌ی چیدمان</Button>
          </>
        ) : (
          <Button variant="ghost" size="sm" icon={<LayoutGrid />} onClick={() => { setDraft(layout); setEditing(true); }}>شخصی‌سازی پیشخوان</Button>
        )}
      </div>

      {error ? <Alert tone="danger">{error}</Alert> : null}

      {shown.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border-strong px-6 py-12 text-center text-sm text-text-muted">
          پیشخوان شما خالی است. «شخصی‌سازی پیشخوان» را بزنید و ویجت اضافه کنید.
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2 md:grid-flow-dense xl:grid-cols-3">
        {shown.map((widget, index) => {
          const meta = byKey.get(widget.key);
          if (!meta) return null;

          return (
            <div
              key={widget.key}
              className={cx(SPAN[widget.size], 'min-w-0', editing && 'relative rounded-2xl outline-2 outline-dashed outline-offset-4 outline-border-strong transition-opacity', dragging === widget.key && 'opacity-40')}
              draggable={editing}
              onDragStart={() => setDragging(widget.key)}
              onDragEnd={() => setDragging(null)}
              onDragOver={(e) => editing && e.preventDefault()}
              onDrop={(e) => onDrop(e, widget.key)}
            >
              {editing ? (
                <div className="mb-2 flex flex-wrap items-center gap-2 rounded-xl bg-surface-raised px-3 py-2 shadow-[var(--shadow-sm)]">
                  <GripVertical className="size-4 cursor-grab text-text-subtle" aria-hidden="true" />
                  <span className="me-auto text-sm font-semibold">{meta.title}</span>
                  {meta.sizes.length > 1 ? (
                    <span className="flex gap-0.5 rounded-lg bg-surface-muted p-0.5" role="group" aria-label={`اندازه‌ی ${meta.title}`}>
                      {meta.sizes.map((s) => (
                        <button key={s} type="button" aria-pressed={widget.size === s}
                          onClick={() => setDraft((d) => d.map((w) => (w.key === widget.key ? { ...w, size: s } : w)))}
                          className={cx('rounded-md px-2 py-0.5 text-xs', widget.size === s ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>
                          {SIZE_LABEL[s]}
                        </button>
                      ))}
                    </span>
                  ) : null}
                  <button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-surface-muted disabled:opacity-30" aria-label={`بردن ${meta.title} به قبل`}><ArrowUp className="size-4" /></button>
                  <button type="button" onClick={() => move(index, 1)} disabled={index === shown.length - 1} className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-surface-muted disabled:opacity-30" aria-label={`بردن ${meta.title} به بعد`}><ArrowDown className="size-4" /></button>
                  <button type="button" onClick={() => setDraft((d) => d.filter((w) => w.key !== widget.key))} className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-danger-soft hover:text-danger" aria-label={`پنهان کردن ${meta.title}`}><EyeOff className="size-4" /></button>
                </div>
              ) : null}
              <div className={cx(editing && 'pointer-events-none select-none')}>
                {nodes[widget.key] ?? (
                  <div className="flex h-40 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-border-strong bg-surface text-center">
                    <p className="font-semibold">{meta.title}</p>
                    <p className="text-xs text-text-muted">پس از ذخیره‌ی چیدمان نمایش داده می‌شود.</p>
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      <Dialog open={adding} onClose={() => setAdding(false)} variant="drawer" title="افزودن ویجت" description="ویجت‌هایی که هنوز روی پیشخوان شما نیستند">
        {hidden.length === 0 ? (
          <p className="text-sm text-text-muted">همه‌ی ویجت‌ها روی پیشخوان هستند.</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {hidden.map((w) => (
              <li key={w.key}>
                <button type="button" onClick={() => { setDraft((d) => [...d, { key: w.key, size: w.default_size }]); setAdding(false); }}
                  className="flex w-full items-start gap-3 rounded-xl border border-border px-4 py-3 text-start transition-colors hover:border-brand hover:bg-brand-soft/40">
                  <Plus className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" />
                  <span>
                    <span className="block text-sm font-semibold">{w.title}</span>
                    <span className="block text-xs text-text-muted">{w.description}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </Dialog>
    </section>
  );
}
