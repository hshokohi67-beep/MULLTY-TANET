'use client';

import { useState, useTransition } from 'react';
import { Settings2 } from 'lucide-react';
import { Button, Card, CardHeader, Checkbox } from '@cafe/ui';
import { saveOccasionPreferences } from '@/app/actions/occasions';
import { KIND_LABELS, type OccasionKind } from '@/lib/occasion-types';

/** Which kinds of occasions the café follows, and whether it gets reminders. */
export function OccasionPreferences({ kinds, notify }: { kinds: OccasionKind[]; notify: boolean }) {
  const [picked, setPicked] = useState(new Set(kinds));
  const [remind, setRemind] = useState(notify);
  const [message, setMessage] = useState<string | null>(null);
  const [pending, start] = useTransition();

  const toggle = (k: OccasionKind) => setPicked((prev) => {
    const next = new Set(prev);
    if (next.has(k)) next.delete(k); else next.add(k);

    return next;
  });

  const save = () => start(async () => {
    const r = await saveOccasionPreferences([...picked], remind);
    setMessage(r.ok ? 'ذخیره شد.' : r.message ?? 'ذخیره نشد.');
  });

  return (
    <Card>
      <CardHeader icon={<Settings2 />} title="چه مناسبت‌هایی را دنبال می‌کنید؟" description="مناسبت‌های خاموش در این صفحه، داشبورد و یادآوری‌ها نمی‌آیند." />
      <div className="flex flex-col gap-4 p-5">
        <div className="flex flex-wrap gap-2">
          {(Object.keys(KIND_LABELS) as OccasionKind[]).map((k) => (
            <label key={k} className="flex cursor-pointer items-center gap-2 rounded-xl border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
              <input type="checkbox" checked={picked.has(k)} onChange={() => toggle(k)} className="accent-[var(--color-brand)]" />
              {KIND_LABELS[k]}
            </label>
          ))}
        </div>
        <Checkbox label="یادآوری در زنگوله و اعلان مرورگر، چند روز پیش از هر مناسبت" checked={remind} onChange={(e) => setRemind(e.target.checked)} />
        <div className="flex items-center gap-3">
          <Button onClick={save} loading={pending}>ذخیره</Button>
          {message ? <span role="status" className="text-sm text-text-muted">{message}</span> : null}
        </div>
      </div>
    </Card>
  );
}
