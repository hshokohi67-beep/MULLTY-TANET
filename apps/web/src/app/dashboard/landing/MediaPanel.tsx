'use client';

import { useRef, useState, useTransition, type ChangeEvent } from 'react';
import { ArrowLeft, ArrowRight, Film, ImagePlus, Trash2, Upload } from 'lucide-react';
import { Alert, Button, Card, CardHeader, IconButton, Spinner, cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { captionLandingMedia, deleteLandingMedia, reorderLandingMedia, uploadLandingMedia } from '@/app/actions/landing';
import type { LandingMedia, LandingMediaItem } from '@/lib/landing-types';

type Kind = 'hero_photo' | 'hero_video' | 'story_photo' | 'gallery';

const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const VIDEO_TYPES = ['video/mp4', 'video/quicktime'];
const PHOTO_MAX = 10 * 1024 * 1024;
const VIDEO_MAX = 8 * 1024 * 1024;
const GALLERY_MAX = 12;

const mb = (bytes: number) => (bytes < 1024 * 1024
  ? `${formatNumber(Math.max(1, Math.round(bytes / 1024)))} کیلوبایت`
  : `${formatNumber(Math.round((bytes / 1024 / 1024) * 10) / 10)} مگابایت`);

/** Checked in the browser first, so a wrong file fails fast (the API checks everything again). */
function problem(kind: Kind, file: File): string | null {
  if (kind === 'hero_video') {
    if (!VIDEO_TYPES.includes(file.type)) return 'ویدیو باید MP4 باشد (از گوشی: «ذخیره به‌صورت MP4» یا خروجی H.264).';
    if (file.size > VIDEO_MAX) return `حجم ویدیو ${mb(file.size)} است؛ حداکثر ۸ مگابایت. کوتاه‌ترش کنید یا با کیفیت کمتر ذخیره کنید.`;
    return null;
  }
  if (!PHOTO_TYPES.includes(file.type)) return 'عکس باید JPG، PNG یا WebP باشد.';
  if (file.size > PHOTO_MAX) return `حجم عکس ${mb(file.size)} است؛ حداکثر ۱۰ مگابایت.`;

  return null;
}

/** Hero photo, hero video, the story photo and the gallery. Uploads apply at once. */
export function MediaPanel({ media, onChange }: { media: LandingMedia; onChange: (m: LandingMedia) => void }) {
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<Kind | null>(null);

  const upload = async (kind: Kind, files: File[]) => {
    setError(null);
    setBusy(kind);
    try {
      for (const file of files) {
        const bad = problem(kind, file);
        if (bad) { setError(bad); break; }
        const body = new FormData();
        body.append('kind', kind);
        body.append('file', file);
        const result = await uploadLandingMedia(body);
        if (!result.ok) { setError(Object.values(result.errors ?? {})[0] || result.message); break; }
        onChange(result.data.media);
      }
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      {error ? <Alert tone="danger">{error}</Alert> : null}

      <Card className="flex flex-col gap-4 p-5">
        <CardHeader title="سردر" description="یک عکس افقی خوش‌نور از فضا یا محصول شاخص (حداقل ۱۶۰۰ پیکسل عرض). بدون عکس، طرح رنگی برند نمایش داده می‌شود." />
        <Slot kind="hero_photo" item={media.hero_photo} busy={busy === 'hero_photo'} onUpload={upload} onRemoved={() => onChange({ ...media, hero_photo: null })} onError={setError} />
      </Card>

      <Card className="flex flex-col gap-4 p-5">
        <CardHeader icon={<Film className="size-5" />} title="ویدیوی سردر (اختیاری)"
          description="یک ویدیوی کوتاه و بی‌صدا (MP4، حداکثر ۸ مگابایت؛ ۱۰ تا ۲۰ ثانیه کافی است). روی اینترنت کند یا حالت کم‌مصرف، به‌جایش همان عکس سردر نمایش داده می‌شود." />
        <Slot kind="hero_video" item={media.hero_video} busy={busy === 'hero_video'} onUpload={upload} onRemoved={() => onChange({ ...media, hero_video: null })} onError={setError} />
        {media.hero_video && !media.hero_photo ? <p className="text-sm text-warning">یک عکس سردر هم بگذارید: تا ویدیو آماده شود (و برای اینترنت‌های کند) همان نمایش داده می‌شود.</p> : null}
      </Card>

      <Card className="flex flex-col gap-4 p-5">
        <CardHeader title="عکس «داستان ما»" description="عکسی عمودی از خودتان، باریستا یا گوشه‌ای از کافه." />
        <Slot kind="story_photo" item={media.story_photo} busy={busy === 'story_photo'} onUpload={upload} onRemoved={() => onChange({ ...media, story_photo: null })} onError={setError} />
      </Card>

      <Gallery photos={media.gallery} busy={busy === 'gallery'} onUpload={upload} onChange={(gallery) => onChange({ ...media, gallery })} onError={setError} />
    </div>
  );
}

function Picker({ kind, multiple, busy, label, onUpload }: { kind: Kind; multiple?: boolean; busy: boolean; label: string; onUpload: (kind: Kind, files: File[]) => void }) {
  const input = useRef<HTMLInputElement>(null);
  const pick = (e: ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? []);
    e.target.value = '';
    if (files.length) onUpload(kind, files);
  };

  return (
    <>
      <input ref={input} type="file" className="sr-only" tabIndex={-1} aria-hidden="true" multiple={multiple}
        accept={kind === 'hero_video' ? VIDEO_TYPES.join(',') : PHOTO_TYPES.join(',')} onChange={pick} />
      <Button variant="secondary" loading={busy} onClick={() => input.current?.click()} icon={kind === 'gallery' ? <ImagePlus className="size-4" /> : <Upload className="size-4" />}>{label}</Button>
    </>
  );
}

/** A two-step delete (no browser confirm dialogs). */
function DeleteButton({ id, onDone, onError }: { id: string; onDone: () => void; onError: (m: string) => void }) {
  const [armed, setArmed] = useState(false);
  const [pending, start] = useTransition();
  if (!armed) return <IconButton label="حذف" onClick={() => setArmed(true)}><Trash2 className="size-4" /></IconButton>;

  return (
    <Button size="sm" variant="danger" loading={pending} onClick={() => start(async () => {
      const r = await deleteLandingMedia(id);
      if (r.ok) onDone(); else onError(r.message);
    })}>حذف شود</Button>
  );
}

function Slot({ kind, item, busy, onUpload, onRemoved, onError }: { kind: Kind; item: LandingMediaItem | null; busy: boolean; onUpload: (k: Kind, f: File[]) => void; onRemoved: () => void; onError: (m: string) => void }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div className={cx('relative flex shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-border-strong bg-surface-muted', kind === 'story_photo' ? 'aspect-[4/5] w-32' : 'aspect-video w-full sm:w-56')}>
        {busy ? <Spinner label="در حال بارگذاری" /> : item ? (
          kind === 'hero_video'
            ? <video src={item.url} muted loop playsInline autoPlay className="size-full object-cover" aria-label="ویدیوی سردر" />
            // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
            : <img src={item.thumb_url} alt="" className="size-full object-cover" />
        ) : <span className="px-3 text-center text-xs text-text-subtle">{kind === 'hero_video' ? 'بدون ویدیو' : 'بدون عکس'}</span>}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Picker kind={kind} busy={busy} onUpload={onUpload} label={item ? 'عوض کردن' : kind === 'hero_video' ? 'انتخاب ویدیو' : 'انتخاب عکس'} />
        {item ? <DeleteButton id={item.id} onDone={onRemoved} onError={onError} /> : null}
        {item ? <span className="text-xs text-text-subtle">{mb(item.bytes)}{item.width ? ` • ${formatNumber(item.width)}×${formatNumber(item.height ?? 0)}` : ''}</span> : null}
      </div>
    </div>
  );
}

function Gallery({ photos, busy, onUpload, onChange, onError }: { photos: LandingMediaItem[]; busy: boolean; onUpload: (k: Kind, f: File[]) => void; onChange: (g: LandingMediaItem[]) => void; onError: (m: string) => void }) {
  const move = (i: number, by: number) => {
    const next = [...photos];
    const j = i + by;
    if (j < 0 || j >= next.length) return;
    [next[i], next[j]] = [next[j], next[i]];
    onChange(next);
    void reorderLandingMedia(next.map((p) => p.id)).then((r) => { if (!r.ok) onError(r.message); });
  };
  const caption = (id: string, text: string) => {
    onChange(photos.map((p) => (p.id === id ? { ...p, caption: text || null } : p)));
    void captionLandingMedia(id, text).then((r) => { if (!r.ok) onError(r.message); });
  };

  return (
    <Card className="flex flex-col gap-4 p-5">
      <CardHeader title={`گالری (${formatNumber(photos.length)} از ${formatNumber(GALLERY_MAX)})`} description="عکس‌های فضا، محصولات و لحظه‌ها. می‌توانید چند عکس را با هم انتخاب کنید." />
      {photos.length < GALLERY_MAX ? <div><Picker kind="gallery" multiple busy={busy} onUpload={onUpload} label="افزودن عکس" /></div> : null}
      {photos.length ? (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {photos.map((p, i) => (
            <li key={p.id} className="flex flex-col gap-2 rounded-xl border border-border p-2">
              {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
              <img src={p.thumb_url} alt="" className="aspect-square w-full rounded-lg object-cover" />
              <input defaultValue={p.caption ?? ''} maxLength={120} placeholder="توضیح (اختیاری)" aria-label={`توضیح عکس ${formatNumber(i + 1)}`}
                onBlur={(e) => { if (e.target.value.trim() !== (p.caption ?? '')) caption(p.id, e.target.value.trim()); }}
                className="h-9 rounded-lg border border-border bg-surface px-2 text-sm outline-none focus:border-brand" />
              <div className="flex items-center">
                <IconButton label="جلوتر" size="sm" onClick={() => move(i, -1)} disabled={i === 0}><ArrowRight className="size-4" /></IconButton>
                <IconButton label="عقب‌تر" size="sm" onClick={() => move(i, 1)} disabled={i === photos.length - 1}><ArrowLeft className="size-4" /></IconButton>
                <span className="ms-auto"><DeleteButton id={p.id} onDone={() => onChange(photos.filter((x) => x.id !== p.id))} onError={onError} /></span>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </Card>
  );
}
