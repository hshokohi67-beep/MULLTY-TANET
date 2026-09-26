'use client';

import { useState, useTransition } from 'react';
import { Plus, X } from 'lucide-react';
import { Button } from '@cafe/ui';
import { addMenuTag, deleteMenuTag } from '@/app/actions/catalog';
import type { MenuTags } from '@/lib/types';

const chip = 'flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft';

/**
 * Diet/allergy tags: the platform's list, then the café's own. A missing one («بدون تخم‌مرغ»,
 * «مناسب کودک»…) can be added right here and is checked at once; deleting one takes it off every
 * product (asked inline first). Checked boxes submit with the product form as `dietary_tags`.
 */
export function MenuTagsField({ lists, selected, readOnly }: { lists: MenuTags; selected: string[]; readOnly: boolean }) {
  const [custom, setCustom] = useState(lists.custom);
  const [checked, setChecked] = useState(() => new Set(selected));
  const [label, setLabel] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState<string | null>(null);
  const [pending, start] = useTransition();

  const toggle = (key: string) => setChecked((prev) => {
    const next = new Set(prev);
    if (next.has(key)) next.delete(key); else next.add(key);

    return next;
  });

  const add = () => start(async () => {
    setError(null);
    const r = await addMenuTag(label.trim());
    if (!r.ok) { setError(r.message); return; }
    setCustom((c) => [...c, r.tag]);
    setChecked((prev) => new Set(prev).add(r.tag.key));
    setLabel('');
  });

  const remove = (id: string, key: string) => start(async () => {
    const r = await deleteMenuTag(id);
    if (!r.ok) { setError(r.message ?? 'حذف نشد.'); return; }
    setCustom((c) => c.filter((t) => t.id !== id));
    setChecked((prev) => { const next = new Set(prev); next.delete(key); return next; });
    setConfirming(null);
  });

  return (
    <fieldset className="sm:col-span-2">
      <legend className="mb-2 text-sm font-medium">برچسب‌های رژیمی و حساسیت</legend>
      <div className="flex flex-wrap gap-2">
        {lists.platform.map((t) => (
          <label key={t.key} className={chip}>
            <input type="checkbox" name="dietary_tags" value={t.key} checked={checked.has(t.key)} onChange={() => toggle(t.key)} className="accent-[var(--color-brand)]" />
            {t.label}
          </label>
        ))}
        {custom.map((t) => (
          <span key={t.id} className="flex items-center gap-1">
            <label className={chip}>
              <input type="checkbox" name="dietary_tags" value={t.key} checked={checked.has(t.key)} onChange={() => toggle(t.key)} className="accent-[var(--color-brand)]" />
              {t.label}
            </label>
            {readOnly ? null : confirming === t.id ? (
              <span className="flex items-center gap-1 text-xs">
                <span className="text-text-muted">از همه‌ی محصولات حذف شود؟</span>
                <Button size="sm" variant="danger" loading={pending} onClick={() => remove(t.id, t.key)}>حذف</Button>
                <Button size="sm" variant="ghost" onClick={() => setConfirming(null)}>نه</Button>
              </span>
            ) : (
              <button type="button" onClick={() => setConfirming(t.id)} aria-label={`حذف برچسب ${t.label}`} className="flex size-7 items-center justify-center rounded-full text-text-subtle hover:bg-surface-muted hover:text-danger">
                <X className="size-3.5" />
              </button>
            )}
          </span>
        ))}
      </div>
      {readOnly ? null : (
        <div className="mt-2.5 flex max-w-sm gap-2">
          <input value={label} onChange={(e) => setLabel(e.target.value)} maxLength={40} placeholder="برچسب تازه، مثلاً «بدون تخم‌مرغ»" aria-label="برچسب تازه"
            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); if (label.trim().length >= 2) add(); } }}
            className="h-9 min-w-0 flex-1 rounded-md border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
          <Button size="sm" variant="secondary" icon={<Plus className="size-4" />} onClick={add} loading={pending} disabled={label.trim().length < 2}>افزودن</Button>
        </div>
      )}
      {error ? <p role="alert" className="mt-1 text-xs text-danger">{error}</p> : null}
      <p className="mt-1 text-xs text-text-muted">در منوی آنلاین روی محصول نمایش داده می‌شود و مشتری با آن فیلتر می‌کند. برچسب‌های تازه برای همه‌ی محصولات این کافه قابل انتخاب‌اند.</p>
    </fieldset>
  );
}
