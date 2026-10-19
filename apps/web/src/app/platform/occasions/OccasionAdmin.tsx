'use client';

import { useState, useTransition } from 'react';
import { CalendarCheck, CalendarPlus, Pencil } from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, SelectField, TextAreaField, TextField } from '@cafe/ui';
import { formatJalaliLong, formatNumber, todayIn } from '@cafe/locale';
import { confirmOccasionDate, saveOccasion, type OccasionInput } from '@/app/actions/platform';
import { JalaliDateField } from '@/components/JalaliDateField';
import { KIND_LABELS, THEME_LABELS, type OccasionKind } from '@/lib/occasion-types';

export interface AdminOccasion extends Omit<OccasionInput, 'ideas'> {
  id: string;
  key: string;
  kind_label: string;
  ideas: string[];
  is_one_off: boolean;
  rule: string | null;
  dates: { id: string; year: number; starts_on: string; ends_on: string; confirmed: boolean; approximate: boolean }[];
}

const day = (d: string) => new Date(`${d}T12:00:00Z`);
const CALENDARS: Record<string, string> = { jalali: 'شمسی', hijri: 'قمری', gregorian: 'میلادی' };
const TONES: Record<string, string> = { festive: 'شاد', calm: 'آرام', mourning: 'سوگواری' };

/** One date row: confirm it as is, or move it (Hijri) and confirm. */
function DateRow({ date }: { date: AdminOccasion['dates'][number] }) {
  const [moving, setMoving] = useState(false);
  const [value, setValue] = useState(date.starts_on);
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const confirm = (startsOn: string) => start(async () => {
    const r = await confirmOccasionDate(date.id, startsOn);
    if (!r.ok) setError(r.message); else setMoving(false);
  });

  return (
    <li className="flex flex-wrap items-center gap-2 py-1.5 text-sm">
      <span className="tabular">{formatJalaliLong(day(date.starts_on), undefined, true)}</span>
      {date.ends_on !== date.starts_on ? <span className="text-text-muted">تا {formatJalaliLong(day(date.ends_on))}</span> : null}
      {date.approximate ? <Badge tone="warning">تقریبی</Badge> : date.confirmed ? <Badge tone="success">تأییدشده</Badge> : null}
      {date.approximate && !moving ? (
        <>
          <Button size="sm" variant="secondary" loading={pending} icon={<CalendarCheck className="size-4" />} onClick={() => confirm(date.starts_on)}>تأیید</Button>
          <Button size="sm" variant="ghost" onClick={() => setMoving(true)}>جابه‌جا کردن</Button>
        </>
      ) : null}
      {moving ? (
        <span className="flex flex-wrap items-end gap-2">
          <JalaliDateField label="روز درست" name={`d-${date.id}`} defaultValue={value} years={2} future onChange={setValue} />
          <Button size="sm" loading={pending} onClick={() => confirm(value)}>ذخیره و تأیید</Button>
        </span>
      ) : null}
      {error ? <span role="alert" className="text-xs text-danger">{error}</span> : null}
    </li>
  );
}

function OccasionForm({ occasion, onDone }: { occasion?: AdminOccasion; onDone: () => void }) {
  const [f, setF] = useState<OccasionInput>(occasion ? { ...occasion } : {
    title: '', description: null, kind: 'national', tone: 'festive', calendar: 'gregorian', month: null, day: null,
    duration_days: 1, lead_days: 7, is_holiday: false, ideas: [], campaign_text: null, theme: null, is_active: true, is_one_off: true, date: todayIn('Asia/Tehran'),
  });
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const set = <K extends keyof OccasionInput>(k: K, v: OccasionInput[K]) => setF((prev) => ({ ...prev, [k]: v }));
  const save = () => start(async () => {
    const r = await saveOccasion(occasion?.id ?? null, f);
    if (!r.ok) { setError(r.message); return; }
    onDone();
  });

  return (
    <div className="flex flex-col gap-3 border-t border-border p-5">
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="عنوان" value={f.title} onChange={(e) => set('title', e.target.value)} maxLength={120} required />
        <SelectField label="نوع" value={f.kind} onChange={(e) => set('kind', e.target.value)}>
          {(Object.keys(KIND_LABELS) as OccasionKind[]).map((k) => <option key={k} value={k}>{KIND_LABELS[k]}</option>)}
        </SelectField>
        <SelectField label="حال‌وهوا" value={f.tone} onChange={(e) => set('tone', e.target.value)}>
          {Object.entries(TONES).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
        </SelectField>
        <SelectField label="تزئین پیشنهادی" value={f.theme ?? ''} onChange={(e) => set('theme', e.target.value || null)}>
          <option value="">بدون تزئین</option>
          {Object.entries(THEME_LABELS).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
        </SelectField>
        <TextField label="چند روز طول می‌کشد" value={String(f.duration_days)} onChange={(e) => set('duration_days', Math.max(1, Number(e.target.value) || 1))} inputMode="numeric" ltr />
        <TextField label="چند روز قبل خبر بدهیم" value={String(f.lead_days)} onChange={(e) => set('lead_days', Math.max(0, Number(e.target.value) || 0))} inputMode="numeric" ltr />
      </div>
      {occasion ? null : <JalaliDateField label="تاریخ (یک‌باره)" name="date" defaultValue={f.date ?? undefined} years={2} future onChange={(v) => set('date', v)} />}
      <TextAreaField label="توضیح برای کافه‌ها" value={f.description ?? ''} onChange={(e) => set('description', e.target.value || null)} />
      <TextAreaField label="ایده‌ها (هر خط یک ایده)" value={f.ideas.join('\n')} onChange={(e) => set('ideas', e.target.value.split('\n').map((x) => x.trim()).filter(Boolean).slice(0, 8))} />
      <TextField label="متن نمونه‌ی کمپین (اختیاری)" value={f.campaign_text ?? ''} onChange={(e) => set('campaign_text', e.target.value || null)} maxLength={300} />
      <div className="flex flex-wrap gap-4">
        <Checkbox label="تعطیل رسمی" checked={f.is_holiday} onChange={(e) => set('is_holiday', e.target.checked)} />
        <Checkbox label="فعال" checked={f.is_active} onChange={(e) => set('is_active', e.target.checked)} />
      </div>
      <div className="flex gap-2"><Button onClick={save} loading={pending} disabled={!f.title.trim()}>ذخیره</Button><Button variant="ghost" onClick={onDone}>بستن</Button></div>
    </div>
  );
}

export function OccasionAdmin({ occasions }: { occasions: AdminOccasion[] }) {
  const [editing, setEditing] = useState<string | null>(null);
  const pending = occasions.flatMap((o) => o.dates).filter((d) => d.approximate).length;

  return (
    <div className="flex flex-col gap-3">
      {pending ? <Alert tone="warning">{formatNumber(pending)} تاریخ قمری منتظر تأیید است.</Alert> : null}
      <Card>
        <CardHeader icon={<CalendarPlus />} title="مناسبت یک‌باره" description="مثلاً روز بازی تیم ملی یا یک رویداد شهری" actions={editing === 'new' ? null : <Button size="sm" onClick={() => setEditing('new')}>افزودن</Button>} />
        {editing === 'new' ? <OccasionForm onDone={() => setEditing(null)} /> : null}
      </Card>
      {occasions.map((o) => (
        <Card key={o.id}>
          <CardHeader
            title={o.title}
            description={[o.kind_label, CALENDARS[o.calendar], TONES[o.tone], `${formatNumber(o.lead_days)} روز قبل`, o.is_active ? null : 'غیرفعال'].filter(Boolean).join(' • ')}
            actions={editing === o.id ? null : <Button size="sm" variant="ghost" icon={<Pencil className="size-4" />} onClick={() => setEditing(o.id)}>ویرایش</Button>}
          />
          {o.dates.length ? <ul className="divide-y divide-border px-5 pb-3">{o.dates.map((d) => <DateRow key={d.id} date={d} />)}</ul> : null}
          {editing === o.id ? <OccasionForm occasion={o} onDone={() => setEditing(null)} /> : null}
        </Card>
      ))}
    </div>
  );
}
