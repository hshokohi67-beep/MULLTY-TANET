'use client';

import { useRef, useState, useTransition } from 'react';
import { ImagePlus, MapPin, Trash2 } from 'lucide-react';
import { Button, Card, CardHeader, EmptyState, cx } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import { removePlaceImage, uploadPlaceImage } from '@/app/actions/marketplace';
import { TONES, cityArt } from '@/components/explore/Art';

export interface PlaceRow { city: string; province: string | null; stores: number; image_url: string | null; image_wide_url: string | null }

/** The city tiles of «خوراک‌گردی», each with a photo the platform designs (or the default art). */
export function PlaceImages({ places }: { places: PlaceRow[] }) {
  return (
    <Card>
      <CardHeader title="تصویر شهرها" description="برای کاشی هر شهر در صفحه‌ی اول خوراک‌گردی یک تصویر طراحی کنید. افقی، دست‌کم ۴۸۰×۳۲۰ پیکسل؛ نام شهر روی آن نوشته می‌شود، پس متن روی تصویر نگذارید." />
      {places.length === 0 ? <EmptyState icon={<MapPin />} title="هنوز شهری فروشگاه ندارد" /> : (
        <ul className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
          {places.map((p, i) => <PlaceTile key={p.city} p={p} index={i} />)}
        </ul>
      )}
    </Card>
  );
}

function PlaceTile({ p, index }: { p: PlaceRow; index: number }) {
  const file = useRef<HTMLInputElement>(null);
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const { icon: Icon, tone } = cityArt(p.city, index);
  const upload = (f: File) => start(async () => {
    const form = new FormData();
    form.append('city', p.city);
    form.append('image', f);
    const r = await uploadPlaceImage(form);
    setError(r.ok ? null : (r.errors?.image || r.message));
  });

  return (
    <li className="flex flex-col gap-2">
      <div className={cx('relative flex h-32 overflow-hidden rounded-2xl p-4', p.image_url ? 'text-on-media' : TONES[tone])}>
        {p.image_url ? (
          <>
            {/* eslint-disable-next-line @next/next/no-img-element -- platform media from object storage */}
            <img src={p.image_url} alt="" className="absolute inset-0 size-full object-cover" />
            <span className="absolute inset-0 bg-gradient-to-t from-scrim via-scrim/30 to-transparent" aria-hidden="true" />
          </>
        ) : <Icon className="absolute -bottom-4 -end-3 size-28 opacity-20" strokeWidth={1.2} aria-hidden="true" />}
        <span className="relative mt-auto">
          <span className="block text-lg font-black">{p.city}</span>
          <span className="text-xs opacity-80">{toPersianDigits(p.stores)} مکان{p.province && p.province !== p.city ? ` • ${p.province}` : ''}</span>
        </span>
      </div>
      <input ref={file} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" aria-label={`تصویر ${p.city}`}
        onChange={(e) => { const f = e.target.files?.[0]; if (f) upload(f); e.target.value = ''; }} />
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" icon={<ImagePlus />} loading={pending} onClick={() => file.current?.click()}>{p.image_url ? 'تعویض تصویر' : 'بارگذاری تصویر'}</Button>
        {p.image_url ? <Button size="sm" variant="ghost" icon={<Trash2 />} disabled={pending} onClick={() => start(async () => { const r = await removePlaceImage(p.city); setError(r.ok ? null : r.message); })}>حذف</Button> : null}
      </div>
      {error ? <p role="alert" className="text-xs text-danger">{error}</p> : null}
    </li>
  );
}
