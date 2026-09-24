'use client';

import { useActionState, useEffect, useMemo, useState, useTransition } from 'react';
import { ArrowDown, ArrowUp, Eye, ExternalLink, GripVertical, ImagePlus, MousePointerClick, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge, Button, Card, Checkbox, cx, Dialog, EmptyState, SelectField, TextAreaField, TextField, type Tone } from '@cafe/ui';
import { formatJalaliDateTime, formatNumber, formatPercent } from '@cafe/locale';
import { deleteStory, reorderStories, saveStory, toggleStory } from '@/app/actions/stories';
import { FormStatus } from '@/components/FormStatus';
import type { FormState } from '@/lib/types';

export interface StaffStory {
  id: string;
  branch_id: string | null;
  image_url: string;
  thumb_url: string;
  width: number;
  height: number;
  caption: string | null;
  link_type: 'none' | 'product' | 'category' | 'url';
  link_target: string | null;
  cta_label: string | null;
  starts_at: string;
  ends_at: string;
  sort: number;
  is_active: boolean;
  status: 'live' | 'scheduled' | 'expired' | 'off';
  views: number;
  clicks: number;
}

type Option = { id: string; name: string };

const STATUS: Record<StaffStory['status'], { label: string; tone: Tone }> = {
  live: { label: 'در حال نمایش', tone: 'success' },
  scheduled: { label: 'زمان‌بندی‌شده', tone: 'info' },
  expired: { label: 'تمام‌شده', tone: 'neutral' },
  off: { label: 'خاموش', tone: 'warning' },
};

/** Stories as phone-shaped cards (live first), with views, clicks and click rate; drag to reorder. */
export function StoriesManager({ stories, products, categories, branches, storeUrl }: {
  stories: StaffStory[];
  products: Option[];
  categories: Option[];
  branches: Option[];
  storeUrl: string;
}) {
  const [editing, setEditing] = useState<StaffStory | 'new' | null>(null);
  const [order, setOrder] = useState(() => stories.map((s) => s.id));
  const [dragging, setDragging] = useState<string | null>(null);
  const [, startReorder] = useTransition();
  const [confirming, setConfirming] = useState<string | null>(null);
  const [busy, startBusy] = useTransition();

  // Keep local order in sync after the server revalidates.
  const [seen, setSeen] = useState(stories);
  if (seen !== stories) {
    setSeen(stories);
    setOrder(stories.map((s) => s.id));
  }

  const byId = useMemo(() => new Map(stories.map((s) => [s.id, s])), [stories]);
  const current = order.map((id) => byId.get(id)).filter((s): s is StaffStory => Boolean(s) && s!.status !== 'expired');
  const expired = stories.filter((s) => s.status === 'expired');

  const move = (id: string, to: number) => {
    const next = order.filter((x) => x !== id);
    next.splice(Math.max(0, Math.min(to, next.length)), 0, id);
    setOrder(next);
    startReorder(async () => { await reorderStories(next); });
  };

  const card = (s: StaffStory, i: number, reorderable: boolean) => {
    const ctr = s.views ? s.clicks / s.views : 0;

    return (
      <li key={s.id}
        draggable={reorderable}
        onDragStart={() => setDragging(s.id)}
        onDragEnd={() => setDragging(null)}
        onDragOver={(e) => { if (reorderable && dragging && dragging !== s.id) e.preventDefault(); }}
        onDrop={() => { if (dragging) move(dragging, order.indexOf(s.id)); }}
        className={cx('group flex flex-col overflow-hidden rounded-3xl border border-border bg-surface shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)]', dragging === s.id && 'opacity-50')}>
        <div className="relative aspect-[9/16] max-h-80 overflow-hidden bg-surface-muted">
          {/* eslint-disable-next-line @next/next/no-img-element -- tenant media */}
          <img src={s.image_url} alt="" loading="lazy" className={cx('size-full object-cover', !s.is_active && 'grayscale')} />
          <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-3 pt-10 text-white">
            {s.caption ? <p className="line-clamp-2 text-sm font-semibold">{s.caption}</p> : <p className="text-xs text-white/70">بدون متن</p>}
            {s.cta_label && s.link_type !== 'none' ? <span className="mt-2 inline-flex rounded-lg bg-white/90 px-2 py-0.5 text-xs font-semibold text-black">{s.cta_label}</span> : null}
          </div>
          <span className="absolute start-2 top-2"><Badge tone={STATUS[s.status].tone} dot>{STATUS[s.status].label}</Badge></span>
          {reorderable ? <GripVertical className="absolute end-2 top-2 size-5 cursor-grab text-white drop-shadow" aria-hidden="true" /> : null}
        </div>
        <div className="flex flex-col gap-2 p-3">
          <dl className="grid grid-cols-3 gap-1 text-center">
            <div><dt className="flex items-center justify-center gap-1 text-[11px] text-text-muted"><Eye className="size-3" aria-hidden="true" />بازدید</dt><dd className="tabular font-bold">{formatNumber(s.views)}</dd></div>
            <div><dt className="flex items-center justify-center gap-1 text-[11px] text-text-muted"><MousePointerClick className="size-3" aria-hidden="true" />کلیک</dt><dd className="tabular font-bold">{formatNumber(s.clicks)}</dd></div>
            <div><dt className="text-[11px] text-text-muted">نرخ کلیک</dt><dd className="tabular font-bold">{s.views ? formatPercent(ctr) : '—'}</dd></div>
          </dl>
          <p className="text-[11px] text-text-subtle">{s.status === 'scheduled' ? `شروع ${formatJalaliDateTime(s.starts_at)}` : s.status === 'expired' ? `پایان ${formatJalaliDateTime(s.ends_at)}` : `تا ${formatJalaliDateTime(s.ends_at)}`}</p>
          <div className="flex items-center gap-1 border-t border-border pt-2">
            {confirming === s.id ? (
              <>
                <Button size="sm" variant="danger" loading={busy} onClick={() => startBusy(async () => { await deleteStory(s.id); setConfirming(null); })}>حذف شود</Button>
                <Button size="sm" variant="ghost" onClick={() => setConfirming(null)}>نه</Button>
              </>
            ) : (
              <>
                <Button size="sm" variant="ghost" icon={<Pencil />} onClick={() => setEditing(s)}>ویرایش</Button>
                {s.status !== 'expired' ? (
                  <Button size="sm" variant="ghost" disabled={busy} onClick={() => startBusy(async () => { await toggleStory(s.id, !s.is_active); })}>{s.is_active ? 'خاموش' : 'روشن'}</Button>
                ) : null}
                <span className="ms-auto flex">
                  {reorderable ? (
                    <>
                      <button type="button" onClick={() => move(s.id, i - 1)} disabled={i === 0} aria-label="جلوتر" className="flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted disabled:opacity-30"><ArrowUp className="size-4" /></button>
                      <button type="button" onClick={() => move(s.id, i + 1)} disabled={i === current.length - 1} aria-label="عقب‌تر" className="flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted disabled:opacity-30"><ArrowDown className="size-4" /></button>
                    </>
                  ) : null}
                  <button type="button" onClick={() => setConfirming(s.id)} aria-label="حذف استوری" className="flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></button>
                </span>
              </>
            )}
          </div>
        </div>
      </li>
    );
  };

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        <Button icon={<Plus />} onClick={() => setEditing('new')}>استوری جدید</Button>
        <a href={storeUrl} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm text-text-muted hover:bg-surface-muted hover:text-text">
          <ExternalLink className="size-4" aria-hidden="true" />دیدن در منوی آنلاین
        </a>
      </div>

      {current.length === 0 && expired.length === 0 ? (
        <Card className="p-6">
          <EmptyState icon={<ImagePlus />} title="هنوز استوری ندارید"
            description="یک عکس خوش‌رنگ از محصول تازه یا فضای کافه بگذارید؛ مشتری با یک لمس به همان محصول می‌رسد."
            action={<Button icon={<Plus />} onClick={() => setEditing('new')}>اولین استوری</Button>} />
        </Card>
      ) : null}

      {current.length ? (
        <section aria-labelledby="h-current">
          <h2 id="h-current" className="mb-3 text-sm font-semibold text-text-muted">در صف نمایش ({formatNumber(current.length)}) • برای تغییر ترتیب بکشید</h2>
          <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{current.map((s, i) => card(s, i, true))}</ul>
        </section>
      ) : null}

      {expired.length ? (
        <section aria-labelledby="h-expired">
          <h2 id="h-expired" className="mb-3 text-sm font-semibold text-text-muted">تمام‌شده در ۳۰ روز اخیر</h2>
          <ul className="grid grid-cols-2 gap-4 opacity-80 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{expired.map((s, i) => card(s, i, false))}</ul>
        </section>
      ) : null}

      <Dialog open={editing !== null} onClose={() => setEditing(null)} variant="drawer" title={editing === 'new' ? 'استوری جدید' : 'ویرایش استوری'}
        description="عکس عمودی (۹:۱۶) بهترین نتیجه را می‌دهد.">
        {editing !== null ? (
          <StoryEditor key={editing === 'new' ? 'new' : editing.id} story={editing === 'new' ? null : editing}
            products={products} categories={categories} branches={branches} onDone={() => setEditing(null)} />
        ) : null}
      </Dialog>
    </>
  );
}

function StoryEditor({ story, products, categories, branches, onDone }: {
  story: StaffStory | null;
  products: Option[];
  categories: Option[];
  branches: Option[];
  onDone: () => void;
}) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveStory.bind(null, story?.id ?? null), { ok: false });
  const [preview, setPreview] = useState<string | null>(story?.image_url ?? null);
  const [caption, setCaption] = useState(story?.caption ?? '');
  const [linkType, setLinkType] = useState<StaffStory['link_type']>(story?.link_type ?? 'none');
  const [cta, setCta] = useState(story?.cta_label ?? '');
  const e = state.errors ?? {};

  useEffect(() => {
    if (state.ok) onDone();
  }, [state.ok, onDone]);

  useEffect(() => () => { if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview); }, [preview]);

  const linkLabel: Record<StaffStory['link_type'], string> = { none: 'بدون لینک', product: 'یک محصول', category: 'یک دسته', url: 'آدرس اینترنتی' };

  return (
    <form action={action} className="flex flex-col gap-5">
      <FormStatus state={state} />

      <div className="flex gap-4">
        {/* Live phone preview */}
        <div className="relative aspect-[9/16] w-32 shrink-0 overflow-hidden rounded-2xl bg-surface-muted shadow-[var(--shadow-md)] ring-4 ring-text/80">
          {preview ? (
            // eslint-disable-next-line @next/next/no-img-element -- local preview or tenant media
            <img src={preview} alt="پیش‌نمایش" className="size-full object-cover" />
          ) : (
            <span className="flex size-full flex-col items-center justify-center gap-1 text-center text-[11px] text-text-subtle"><ImagePlus className="size-6" aria-hidden="true" />عکس را انتخاب کنید</span>
          )}
          <span className="absolute inset-x-2 top-2 h-0.5 rounded-full bg-white/70" aria-hidden="true" />
          <div className="absolute inset-x-0 bottom-0 flex flex-col gap-1.5 bg-gradient-to-t from-black/80 to-transparent p-2 pt-8 text-white">
            {caption ? <p className="line-clamp-3 text-[10px] font-bold leading-4">{caption}</p> : null}
            {linkType !== 'none' ? <span className="rounded-md bg-white py-1 text-center text-[9px] font-semibold text-black">{cta || 'مشاهده'}</span> : null}
          </div>
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-3">
          <label className="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-border-strong px-3 py-5 text-center text-sm text-text-muted transition-colors hover:border-brand hover:text-brand has-[:focus-visible]:shadow-[var(--focus-ring)]">
            <ImagePlus className="size-5" aria-hidden="true" />
            {story ? 'عوض کردن عکس (اختیاری)' : 'انتخاب عکس'}
            <span className="text-[11px] text-text-subtle">JPG، PNG یا WebP تا ۸ مگابایت • خودکار بهینه می‌شود</span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required={!story} className="sr-only"
              onChange={(ev) => { const f = ev.target.files?.[0]; if (f) setPreview(URL.createObjectURL(f)); }} />
          </label>
          {e.image ? <p className="text-xs text-danger">{e.image}</p> : null}
        </div>
      </div>

      <TextAreaField label="متن روی استوری" name="caption" rows={2} maxLength={200} value={caption} onChange={(ev) => setCaption(ev.target.value)}
        hint={`${formatNumber(caption.length)} از ۲۰۰ نویسه`} error={e.caption} />

      <fieldset>
        <legend className="mb-2 text-sm font-medium">دکمه‌ی استوری به کجا برود؟</legend>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {(Object.keys(linkLabel) as StaffStory['link_type'][]).map((t) => (
            <label key={t} className={cx('cursor-pointer rounded-lg border px-2 py-2 text-center text-sm transition-colors has-[:focus-visible]:shadow-[var(--focus-ring)]', linkType === t ? 'border-brand bg-brand-soft font-semibold' : 'border-border hover:border-border-strong')}>
              <input type="radio" name="link_type" value={t} checked={linkType === t} onChange={() => setLinkType(t)} className="sr-only" />
              {linkLabel[t]}
            </label>
          ))}
        </div>
      </fieldset>

      {linkType === 'product' ? (
        <SelectField label="محصول" name="link_product" defaultValue={story?.link_type === 'product' ? story.link_target ?? '' : ''} required error={e.link_target}>
          <option value="">انتخاب کنید…</option>
          {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </SelectField>
      ) : null}
      {linkType === 'category' ? (
        <SelectField label="دسته" name="link_category" defaultValue={story?.link_type === 'category' ? story.link_target ?? '' : ''} required error={e.link_target}>
          <option value="">انتخاب کنید…</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </SelectField>
      ) : null}
      {linkType === 'url' ? (
        <TextField label="آدرس" name="link_url" type="url" ltr placeholder="https://instagram.com/…" defaultValue={story?.link_type === 'url' ? story.link_target ?? '' : ''} required error={e.link_target} />
      ) : null}
      {linkType !== 'none' ? (
        <TextField label="متن دکمه" name="cta_label" maxLength={30} value={cta} onChange={(ev) => setCta(ev.target.value)} placeholder="مثلاً: سفارش بده" error={e.cta_label} />
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2">
        <SelectField label="مدت نمایش" name="duration" defaultValue={story ? 'keep' : '24'} error={e.ends_at}
          hint={story ? `اکنون تا ${formatJalaliDateTime(story.ends_at)}` : 'از همین حالا'}>
          {story ? <option value="keep">بدون تغییر</option> : null}
          <option value="24">۲۴ ساعت</option>
          <option value="72">۳ روز</option>
          <option value="168">۱ هفته</option>
          <option value="336">۲ هفته</option>
          <option value="720">۱ ماه</option>
        </SelectField>
        {branches.length > 1 ? (
          <SelectField label="شعبه" name="branch_id" defaultValue={story?.branch_id ?? ''}>
            <option value="">همه‌ی شعبه‌ها</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
        ) : <input type="hidden" name="branch_id" value={story?.branch_id ?? ''} />}
      </div>

      <Checkbox name="is_active" defaultChecked={story?.is_active ?? true} label="نمایش در منوی آنلاین" />

      <div className="flex gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending}>{story ? 'ذخیره' : 'انتشار استوری'}</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
      </div>
    </form>
  );
}
