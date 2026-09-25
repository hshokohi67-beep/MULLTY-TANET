'use client';

import Link from 'next/link';
import { useState, useTransition } from 'react';
import { Compass, EyeOff, Eye, Sparkles } from 'lucide-react';
import { Badge, Button, Card, Dialog, EmptyState, TextField } from '@cafe/ui';
import { formatJalaliDate, toPersianDigits } from '@cafe/locale';
import { moderate } from '@/app/actions/marketplace';
import { JalaliDateField } from '@/components/JalaliDateField';

export interface ListingRow {
  tenant: { id: string; name: string; slug: string };
  is_listed: boolean; headline: string | null; live_branches: number;
  hidden_at: string | null; hidden_reason: string | null; featured_until: string | null;
}

type Action = { kind: 'hide' | 'feature'; row: ListingRow };

export function ModerationTable({ rows }: { rows: ListingRow[] }) {
  const [action, setAction] = useState<Action | null>(null);
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [reason, setReason] = useState('');
  const run = (tenantId: string, kind: 'hide' | 'unhide' | 'feature', value: string | null) => start(async () => {
    const r = await moderate(tenantId, kind, value);
    if (r.ok) { setAction(null); setError(null); setReason(''); } else setError(r.message);
  });

  if (rows.length === 0) return <Card><EmptyState icon={<Compass />} title="هنوز کافه‌ای برای بازارگاه ثبت‌نام نکرده" /></Card>;

  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[44rem] text-sm">
          <thead className="bg-surface-muted/60 text-xs text-text-muted">
            <tr>
              <th className="px-4 py-2.5 text-start font-medium">کافه</th>
              <th className="px-4 py-2.5 text-start font-medium">وضعیت</th>
              <th className="px-4 py-2.5 text-start font-medium">ویژه تا</th>
              <th className="px-4 py-2.5 text-end font-medium">کارها</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map((r) => (
              <tr key={r.tenant.id}>
                <td className="px-4 py-3">
                  <p className="font-medium">{r.tenant.name}</p>
                  {r.headline ? <p className="line-clamp-1 text-xs text-text-muted">{r.headline}</p> : null}
                </td>
                <td className="px-4 py-3">
                  {r.hidden_at ? <Badge tone="danger" dot>متوقف: {r.hidden_reason}</Badge>
                    : r.live_branches ? <Badge tone="success" dot>نمایش در {toPersianDigits(r.live_branches)} شعبه</Badge>
                      : <Badge dot>{r.is_listed ? 'ناقص' : 'خاموش توسط کافه'}</Badge>}
                </td>
                <td className="px-4 py-3 text-text-muted">{r.featured_until ? formatJalaliDate(r.featured_until) : '—'}</td>
                <td className="px-4 py-3">
                  <div className="flex justify-end gap-1">
                    {r.live_branches ? <Link href={`/explore/${r.tenant.slug}`} target="_blank" className="inline-flex h-8 items-center rounded-lg px-2.5 text-sm text-brand hover:bg-brand-soft">مشاهده</Link> : null}
                    <Button size="sm" variant="ghost" icon={<Sparkles />} onClick={() => setAction({ kind: 'feature', row: r })}>ویژه</Button>
                    {r.hidden_at
                      ? <Button size="sm" variant="secondary" icon={<Eye />} loading={pending} onClick={() => run(r.tenant.id, 'unhide', null)}>نمایش دوباره</Button>
                      : <Button size="sm" variant="ghost" icon={<EyeOff />} onClick={() => setAction({ kind: 'hide', row: r })}>توقف نمایش</Button>}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Dialog open={action !== null} onClose={() => setAction(null)} size="sm" title={action ? `${action.kind === 'hide' ? 'توقف نمایش' : 'نمایش ویژه'} • ${action.row.tenant.name}` : ''}>
        {action?.kind === 'hide' ? (
          <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); run(action.row.tenant.id, 'hide', reason); }}>
            <TextField label="دلیل (به کافه‌دار نشان داده می‌شود)" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={200} />
            {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
            <Button type="submit" variant="danger" loading={pending}>توقف نمایش</Button>
          </form>
        ) : null}
        {action?.kind === 'feature' ? (
          <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); run(action.row.tenant.id, 'feature', `${String(new FormData(e.currentTarget).get('until'))}T23:59:00+03:30`); }}>
            <JalaliDateField label="ویژه تا تاریخ" name="until" years={2} />
            {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
            <div className="flex gap-2">
              <Button type="submit" loading={pending}>ثبت</Button>
              {action.row.featured_until ? <Button variant="ghost" loading={pending} onClick={() => run(action.row.tenant.id, 'feature', null)}>لغو ویژه</Button> : null}
            </div>
          </form>
        ) : null}
      </Dialog>
    </Card>
  );
}
