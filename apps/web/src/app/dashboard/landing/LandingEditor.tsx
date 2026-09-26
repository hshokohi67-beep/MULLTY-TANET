'use client';

import { useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { ExternalLink, Image as ImageIcon, LayoutList, Monitor, Moon, Palette, Save, Shuffle, Smartphone } from 'lucide-react';
import { Alert, Badge, Button, Card, brandTokens, cx } from '@cafe/ui';
import { saveLanding } from '@/app/actions/landing';
import { Landing } from '@/components/store/landing/Landing';
import type { LandingContent, LandingDesign, LandingMedia, LandingSection, StaffLanding } from '@/lib/landing-types';
import type { Menu, Storefront } from '@/lib/storefront-types';
import { ContentPanel } from './ContentPanel';
import { DesignPanel, shuffledDesign } from './DesignPanel';
import { MediaPanel } from './MediaPanel';

export interface Draft { is_published: boolean; design: LandingDesign; content: LandingContent; sections: LandingSection[] }

const TABS = [
  { key: 'design', label: 'ظاهر', icon: Palette },
  { key: 'content', label: 'متن و بخش‌ها', icon: LayoutList },
  { key: 'media', label: 'عکس و ویدیو', icon: ImageIcon },
] as const;
type Tab = (typeof TABS)[number]['key'];

const draftOf = (l: StaffLanding): Draft => ({ is_published: l.is_published, design: l.design, content: l.content, sections: l.sections });

/**
 * The landing page editor: three panels (look, words and sections, photos) on one side and a live
 * preview on the other, rendered with the storefront's own components. Nothing is public until
 * «ذخیره» with «منتشر شود» on; photos upload at once (they're only shown after publishing).
 */
export function LandingEditor({ initial, tenant, store, menu, storeUrl }: { initial: StaffLanding; tenant: string; store: Storefront; menu: Menu | null; storeUrl: string }) {
  const [draft, setDraft] = useState<Draft>(() => draftOf(initial));
  const [saved, setSaved] = useState<string>(() => JSON.stringify(draftOf(initial)));
  const [media, setMedia] = useState<LandingMedia>(initial.media);
  const [tab, setTab] = useState<Tab>('design');
  const [msg, setMsg] = useState<{ tone: 'success' | 'danger'; text: string; errors?: string[] } | null>(null);
  const [pending, start] = useTransition();
  const dirty = JSON.stringify(draft) !== saved;
  const live = (JSON.parse(saved) as Draft).is_published;

  // Leaving with unsaved changes asks first (the browser's own prompt).
  useEffect(() => {
    if (!dirty) return;
    const warn = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener('beforeunload', warn);

    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const save = () => start(async () => {
    const result = await saveLanding(draft);
    if (result.ok) {
      const next = draftOf(result.data);
      setDraft(next);
      setSaved(JSON.stringify(next));
      setMedia(result.data.media);
      setMsg({ tone: 'success', text: next.is_published ? 'ذخیره شد؛ تا یک دقیقه‌ی دیگر روی سایت دیده می‌شود.' : 'ذخیره شد (هنوز منتشر نشده است).' });
    } else {
      setMsg({ tone: 'danger', text: result.message, errors: Object.values(result.errors ?? {}).filter(Boolean).slice(0, 5) });
    }
  });

  return (
    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <div className="flex min-w-0 flex-col gap-4">
        <Card className="flex flex-wrap items-center gap-3 p-4">
          <label className="flex cursor-pointer items-center gap-3">
            <input type="checkbox" className="peer sr-only" checked={draft.is_published} onChange={(e) => setDraft({ ...draft, is_published: e.target.checked })} />
            <span aria-hidden="true" className="relative h-6 w-11 rounded-full bg-border-strong transition-colors after:absolute after:start-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-surface after:shadow after:transition-all peer-checked:bg-success peer-checked:after:start-[1.375rem] peer-focus-visible:outline-2 peer-focus-visible:outline-brand" />
            <span className="font-semibold">صفحه‌ی معرفی منتشر شود</span>
          </label>
          <Badge tone={live ? 'success' : 'neutral'} dot>{live ? 'منتشر شده' : 'پیش‌نویس'}</Badge>
          {dirty ? <Badge tone="warning">تغییرات ذخیره نشده</Badge> : null}
          <div className="ms-auto flex gap-2">
            <a href={storeUrl} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-1.5 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted">
              <ExternalLink className="size-4" aria-hidden="true" />دیدن سایت
            </a>
            <Button onClick={save} loading={pending} disabled={!dirty && !pending} icon={<Save className="size-4" />}>ذخیره</Button>
          </div>
          {!draft.is_published ? <p className="w-full text-sm text-text-muted">تا منتشر نشده، آدرس اصلی کافه همان منو را نشان می‌دهد.</p> : null}
        </Card>

        {msg ? (
          <Alert tone={msg.tone}>
            {msg.text}
            {msg.errors?.length ? <ul className="mt-1 list-inside list-disc">{msg.errors.map((e, i) => <li key={i}>{e}</li>)}</ul> : null}
          </Alert>
        ) : null}

        <nav role="tablist" aria-label="بخش‌های ویرایشگر" className="flex gap-1 rounded-xl bg-surface-muted p-1 text-sm">
          {TABS.map((t) => (
            <button key={t.key} role="tab" type="button" aria-selected={tab === t.key} onClick={() => setTab(t.key)}
              className={cx('flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 transition-colors', tab === t.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>
              <t.icon className="size-4" aria-hidden="true" />{t.label}
            </button>
          ))}
        </nav>

        <div role="tabpanel">
          {tab === 'design' ? (
            <DesignPanel design={draft.design} onChange={(design) => setDraft({ ...draft, design })}
              onShuffle={() => setDraft({ ...draft, ...shuffledDesign(draft) })} />
          ) : tab === 'content' ? (
            <ContentPanel draft={draft} onChange={setDraft} products={menu?.products ?? []} galleryCount={media.gallery.length} onGoToMedia={() => setTab('media')} />
          ) : (
            <MediaPanel media={media} onChange={setMedia} />
          )}
        </div>

        <Button variant="secondary" className="xl:hidden" onClick={() => setDraft({ ...draft, ...shuffledDesign(draft) })} icon={<Shuffle className="size-4" />}>یک ترکیب تازه پیشنهاد بده</Button>
      </div>

      <Preview tenant={tenant} store={store} menu={menu} draft={draft} media={media} />
    </div>
  );
}

/** The live preview: a phone, or a scaled-down desktop; optionally in the dark palette. */
function Preview({ tenant, store, menu, draft, media }: { tenant: string; store: Storefront; menu: Menu | null; draft: Draft; media: LandingMedia }) {
  const [device, setDevice] = useState<'phone' | 'desktop'>('phone');
  const [dark, setDark] = useState(false);
  const box = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(720);

  useEffect(() => {
    const el = box.current;
    if (!el) return;
    const ro = new ResizeObserver(([e]) => setWidth(e.contentRect.width));
    ro.observe(el);

    return () => ro.disconnect();
  }, []);

  // The café's brand colour inside the preview (the panel itself keeps the platform's).
  const css = useMemo(() => {
    const light = brandTokens(store.branding?.primary_color, 'light');
    const darkT = brandTokens(store.branding?.primary_color, 'dark');
    const vars = (t: NonNullable<typeof light>) => `--color-brand:${t.brand};--color-brand-strong:${t.brandStrong};--color-brand-soft:${t.brandSoft};--color-on-brand:${t.onBrand};`;

    return light && darkT ? `.landing-preview{${vars(light)}}.landing-preview[data-theme='dark']{${vars(darkT)}}` : '';
  }, [store.branding?.primary_color]);

  const landing = { ...draft, media };
  const desktopWidth = 1280;
  const scale = Math.min(1, width / desktopWidth);
  const height = 680;

  return (
    <div className="flex min-w-0 flex-col gap-3 xl:sticky xl:top-20">
      <div className="flex items-center gap-2">
        <div role="group" aria-label="اندازه‌ی پیش‌نمایش" className="flex gap-1 rounded-xl bg-surface-muted p-1">
          {([['phone', 'موبایل', Smartphone], ['desktop', 'دسکتاپ', Monitor]] as const).map(([key, label, Icon]) => (
            <button key={key} type="button" aria-pressed={device === key} onClick={() => setDevice(key)}
              className={cx('inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm', device === key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>
              <Icon className="size-4" aria-hidden="true" />{label}
            </button>
          ))}
        </div>
        <button type="button" aria-pressed={dark} onClick={() => setDark(!dark)}
          className={cx('inline-flex h-9 items-center gap-1.5 rounded-xl border px-3 text-sm', dark ? 'border-brand bg-brand-soft font-semibold' : 'border-border text-text-muted')}>
          <Moon className="size-4" aria-hidden="true" />حالت تیره
        </button>
        <span className="ms-auto text-xs text-text-subtle">پیش‌نمایش زنده</span>
      </div>

      <style dangerouslySetInnerHTML={{ __html: css }} />
      <div ref={box} className="w-full">
        {device === 'phone' ? (
          <div className="mx-auto w-[390px] max-w-full overflow-hidden rounded-[2.25rem] border-[10px] border-text shadow-[var(--shadow-lg)]">
            <div className="landing-preview h-[720px] overflow-y-auto overscroll-contain" data-theme={dark ? 'dark' : undefined}>
              <Landing tenant={tenant} store={store} menu={menu} landing={landing} preview />
            </div>
          </div>
        ) : (
          <div className="overflow-hidden rounded-2xl border border-border shadow-[var(--shadow-md)]" style={{ height }}>
            <div className="landing-preview overflow-y-auto overscroll-contain" data-theme={dark ? 'dark' : undefined}
              style={{ width: desktopWidth, height: height / scale, transform: `scale(${scale})`, transformOrigin: 'top right' }}>
              <Landing tenant={tenant} store={store} menu={menu} landing={landing} preview />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
