'use client';

import { useEffect, useState, useTransition } from 'react';
import { AlertTriangle, CheckCircle2, Megaphone, MessageSquareText, Plug, Plus, Send, ShieldCheck, Users, X } from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, EmptyState, SelectField, StatTile, TextAreaField, TextField, cx } from '@cafe/ui';
import { JALALI_MONTHS, addDays, formatJalaliDateTime, formatNumber, toPersianDigits, todayIn } from '@cafe/locale';
import { ClockSelect } from '@cafe/ui';
import { campaignCommand, countAudience, loadSmsLogs, saveSmsAccount, saveSmsCampaign, saveSmsTemplates, sendTestSms } from '@/app/actions/sms';
import { JalaliDateField } from '@/components/JalaliDateField';
import { localToIso } from '@/lib/operations-types';
import { SMS_ERRORS, SMS_KIND_LABELS, smsParts, type SmsAudience, type SmsCampaignView, type SmsCentre as Data, type SmsLogRow } from '@/lib/sms-types';

type Tab = 'connect' | 'auto' | 'campaigns' | 'log';

export function SmsCentre({ data, ownerPhone, timezone }: { data: Data; ownerPhone: string; timezone: string }) {
  const [tab, setTab] = useState<Tab>(data.account ? 'auto' : 'connect');
  const connected = Boolean(data.account?.is_active);
  const tabs: { key: Tab; label: string }[] = [
    { key: 'connect', label: 'اتصال پنل پیامک' }, { key: 'auto', label: 'پیام‌های خودکار' }, { key: 'campaigns', label: 'کمپین‌ها' }, { key: 'log', label: 'گزارش ارسال' },
  ];

  return (
    <div className="flex flex-col gap-6">
      {!connected ? (
        <Alert tone="warning" title="پنل پیامک کافه وصل نیست">پیامک‌های خودکار و کمپین‌ها فقط با پنل و خط خود کافه ارسال می‌شوند؛ کد ورود مشتری‌ها همیشه از خط کافه‌یار می‌رود.</Alert>
      ) : null}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="ارسال‌شده (۳۰ روز)" value={formatNumber(data.stats.sent)} icon={<Send />} hint={`${formatNumber(data.stats.parts)} پیامک (بخش)`} />
        <StatTile label="ناموفق" value={formatNumber(data.stats.failed)} icon={<AlertTriangle />} hint={data.stats.skipped ? `${formatNumber(data.stats.skipped)} بدون اتصال پنل` : 'بدون مورد پرخطر'} />
        <StatTile label="مشتریان با اجازه‌ی تبلیغ" value={formatNumber(data.stats.opted_in)} icon={<Users />} hint="فقط به این‌ها کمپین می‌رود" />
        <StatTile label="وضعیت پنل" value={connected ? (data.account?.verified_at ? 'آزمایش‌شده' : 'وصل') : 'وصل نیست'} icon={<Plug />} hint={data.account?.last_error ? `آخرین خطا: ${SMS_ERRORS[data.account.last_error] ?? data.account.last_error}` : undefined} />
      </div>

      <nav role="tablist" aria-label="بخش‌های پیامک" className="flex gap-1 overflow-x-auto rounded-xl bg-surface-muted p-1 text-sm">
        {tabs.map((t) => (
          <button key={t.key} role="tab" type="button" aria-selected={tab === t.key} onClick={() => setTab(t.key)}
            className={cx('shrink-0 rounded-lg px-4 py-2', tab === t.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{t.label}</button>
        ))}
      </nav>

      {tab === 'connect' ? <Connect data={data} ownerPhone={ownerPhone} /> : null}
      {tab === 'auto' ? <Automatic data={data} /> : null}
      {tab === 'campaigns' ? <Campaigns data={data} timezone={timezone} connected={connected} /> : null}
      {tab === 'log' ? <Log timezone={timezone} /> : null}
    </div>
  );
}

function Connect({ data, ownerPhone }: { data: Data; ownerPhone: string }) {
  const [provider, setProvider] = useState(data.account?.provider ?? data.drivers[0]?.key ?? 'kavenegar');
  const [fields, setFields] = useState<Record<string, string>>({});
  const [active, setActive] = useState(data.account?.is_active ?? true);
  const [phone, setPhone] = useState(ownerPhone);
  const [msg, setMsg] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);
  const [pending, start] = useTransition();
  const driver = data.drivers.find((d) => d.key === provider);
  const same = data.account?.provider === provider;

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[3fr_2fr]">
      <Card>
        <CardHeader title="پنل پیامک کافه" description="حساب پنل پیامکی خودتان را وصل کنید. هزینه و مسئولیت ارسال با حساب خود کافه است." />
        <form className="flex flex-col gap-5 p-5" onSubmit={(e) => {
          e.preventDefault();
          start(async () => {
            const r = await saveSmsAccount(provider, fields, active);
            setMsg(r.ok ? { tone: 'success', text: 'ذخیره شد. حالا یک پیام آزمایشی بفرستید.' } : { tone: 'danger', text: r.message });
            if (r.ok) setFields({});
          });
        }}>
          <fieldset className="grid gap-2 sm:grid-cols-3">
            <legend className="mb-2 text-sm font-medium">پنل</legend>
            {data.drivers.map((d) => (
              <label key={d.key} className={cx('flex cursor-pointer flex-col gap-0.5 rounded-xl border p-3 text-sm transition-colors', provider === d.key ? 'border-brand bg-brand-soft' : 'border-border hover:bg-surface-muted')}>
                <input type="radio" name="provider" className="sr-only" checked={provider === d.key} onChange={() => { setProvider(d.key); setFields({}); }} />
                <span className="font-semibold">{d.label}</span>
                <span className="text-xs text-text-muted" dir="ltr">{d.site}</span>
              </label>
            ))}
          </fieldset>
          <div className="grid gap-4 sm:grid-cols-2">
            {driver?.fields.map((f) => {
              const stored = same ? data.account?.fields[f.key] : undefined;

              return (
                <TextField key={f.key} label={f.label} ltr type={f.secret ? 'password' : 'text'} autoComplete="off"
                  value={fields[f.key] ?? (f.secret ? '' : stored?.value ?? '')}
                  placeholder={f.secret && stored?.is_set ? stored.masked ?? '' : ''}
                  hint={f.secret && stored?.is_set ? 'ثبت شده؛ خالی بگذارید تا تغییر نکند.' : f.key === 'sender' ? 'شماره‌ی خطی که پیامک‌ها با آن ارسال می‌شود.' : undefined}
                  onChange={(e) => setFields((x) => ({ ...x, [f.key]: e.target.value }))} />
              );
            })}
          </div>
          <Checkbox label="ارسال از این پنل فعال باشد" checked={active} onChange={(e) => setActive(e.target.checked)} />
          {msg ? <Alert tone={msg.tone}>{msg.text}</Alert> : null}
          <div><Button type="submit" loading={pending}>ذخیره</Button></div>
        </form>
      </Card>

      <div className="flex flex-col gap-6">
        <Card>
          <CardHeader title="پیام آزمایشی" description="بعد از ذخیره، یک پیام واقعی از خط کافه بفرستید تا مطمئن شوید درست وصل شده است." />
          <div className="flex flex-col gap-3 p-5">
            <TextField label="شماره‌ی گیرنده" ltr inputMode="tel" value={phone} onChange={(e) => setPhone(e.target.value)} />
            <Button variant="secondary" icon={<Send />} loading={pending} disabled={!data.account}
              onClick={() => start(async () => {
                const r = await sendTestSms(phone);
                setMsg(r.ok ? (r.data.sent ? { tone: 'success', text: 'پیام آزمایشی ارسال شد.' } : { tone: 'danger', text: 'ارسال نشد؛ اطلاعات پنل یا اعتبار حساب را بررسی کنید (جزئیات در «گزارش ارسال»).' }) : { tone: 'danger', text: r.message });
              })}>ارسال آزمایشی</Button>
            {data.account?.verified_at ? <p className="flex items-center gap-1.5 text-sm text-success"><CheckCircle2 className="size-4" aria-hidden="true" />آزمایش موفق در {formatJalaliDateTime(data.account.verified_at)}</p> : null}
          </div>
        </Card>
        <Card>
          <div className="flex gap-3 p-5 text-sm leading-7 text-text-muted">
            <ShieldCheck className="mt-1 size-5 shrink-0 text-brand" aria-hidden="true" />
            <p>اطلاعات پنل رمزنگاری‌شده ذخیره می‌شود و هیچ‌وقت نمایش داده نمی‌شود. کد ورود مشتری‌ها از خط کافه‌یار می‌رود و به این پنل وابسته نیست.</p>
          </div>
        </Card>
      </div>
    </div>
  );
}

function Automatic({ data }: { data: Data }) {
  const [state, setState] = useState(() => Object.fromEntries(data.templates.map((t) => [t.key, { enabled: t.enabled, body: t.body }])));
  const [msg, setMsg] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const preview = (body: string) => body.replace('{name}', 'سارا').replace('{number}', '۲۳').replace('{cafe}', 'کافه‌ی شما').replace('{gift}', '۵۰٬۰۰۰ تومان');

  return (
    <Card>
      <CardHeader title="پیام‌های خودکار" description="هر پیام را جداگانه روشن کنید و متنش را به سلیقه‌ی خودتان بنویسید. عبارت‌های داخل {} خودکار پر می‌شوند." />
      <div className="flex flex-col divide-y divide-border">
        {data.templates.map((t) => {
          const s = state[t.key];

          return (
            <div key={t.key} className="grid gap-4 p-5 lg:grid-cols-[1fr_1fr]">
              <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-3">
                  <div><p className="font-bold">{t.label}</p><p className="text-sm text-text-muted">{t.hint}</p></div>
                  <Checkbox label="روشن" checked={s.enabled} onChange={(e) => setState((x) => ({ ...x, [t.key]: { ...s, enabled: e.target.checked } }))} />
                </div>
                <TextAreaField label="متن پیام" rows={3} maxLength={500} value={s.body} onChange={(e) => setState((x) => ({ ...x, [t.key]: { ...s, body: e.target.value } }))}
                  hint={`${toPersianDigits([...s.body].length)} نویسه • ${toPersianDigits(smsParts(s.body))} پیامک`} />
                <p className="flex flex-wrap gap-1.5 text-xs">
                  {t.placeholders.map((p) => (
                    <button key={p.key} type="button" onClick={() => setState((x) => ({ ...x, [t.key]: { ...s, body: `${s.body} {${p.key}}` } }))} className="rounded-full border border-border px-2 py-0.5 text-text-muted hover:border-brand hover:text-text">
                      {`{${p.key}}`} {p.label}
                    </button>
                  ))}
                  <button type="button" onClick={() => setState((x) => ({ ...x, [t.key]: { ...s, body: t.default } }))} className="rounded-full px-2 py-0.5 text-brand hover:underline">متن پیش‌فرض</button>
                </p>
              </div>
              <div className="flex flex-col gap-1.5">
                <p className="text-xs font-semibold text-text-muted">پیش‌نمایش روی گوشی</p>
                <div className={cx('max-w-sm rounded-2xl rounded-ss-sm bg-surface-muted p-4 text-sm leading-7', !s.enabled && 'opacity-50')}>{preview(s.body)}</div>
              </div>
            </div>
          );
        })}
      </div>
      <div className="flex items-center gap-3 border-t border-border p-5">
        <Button loading={pending} onClick={() => start(async () => { const r = await saveSmsTemplates(state); setMsg(r.ok ? 'ذخیره شد.' : r.message); })}>ذخیره</Button>
        {msg ? <span role="status" className="text-sm text-text-muted">{msg}</span> : null}
      </div>
    </Card>
  );
}

const STATUS: Record<SmsCampaignView['status'], { label: string; tone: 'neutral' | 'info' | 'warning' | 'success' | 'danger' }> = {
  draft: { label: 'پیش‌نویس', tone: 'neutral' }, scheduled: { label: 'زمان‌بندی‌شده', tone: 'info' }, sending: { label: 'در حال ارسال', tone: 'warning' },
  done: { label: 'ارسال شد', tone: 'success' }, cancelled: { label: 'لغو شد', tone: 'danger' },
};

function Campaigns({ data, timezone, connected }: { data: Data; timezone: string; connected: boolean }) {
  const [editing, setEditing] = useState<SmsCampaignView | 'new' | null>(null);

  return (
    <div className="flex flex-col gap-4">
      <Alert tone="info" title="قوانین ارسال تبلیغاتی">
        {`فقط به مشتریانی که در حساب خود اجازه‌ی پیام تبلیغاتی داده‌اند؛ فقط بین ساعت ${toPersianDigits(data.rules.quiet_to)} صبح تا ${toPersianDigits(data.rules.quiet_from)}؛ حداکثر ${formatNumber(data.rules.daily_cap)} پیامک در روز؛ و «${data.rules.footer}» همیشه به انتهای پیام اضافه می‌شود.`}
      </Alert>
      <div className="flex justify-end"><Button icon={<Plus />} onClick={() => setEditing('new')}>کمپین تازه</Button></div>
      {editing ? <CampaignEditor key={editing === 'new' ? 'new' : editing.id} data={data} timezone={timezone} connected={connected} initial={editing === 'new' ? null : editing} onDone={() => setEditing(null)} /> : null}
      {data.campaigns.length === 0 && !editing ? (
        <Card><EmptyState icon={<Megaphone />} title="هنوز کمپینی نساخته‌اید" description="مثلاً «لاته‌ی پاییزی این هفته ۲۰٪ تخفیف» برای همه‌ی مشتریانی که اجازه داده‌اند." /></Card>
      ) : (
        <ul className="flex flex-col gap-3">
          {data.campaigns.map((c) => (
            <li key={c.id} className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] sm:flex-row sm:items-center">
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 font-bold">{c.name}<Badge tone={STATUS[c.status].tone} dot>{STATUS[c.status].label}</Badge></p>
                <p className="mt-1 line-clamp-2 text-sm text-text-muted">{c.body}</p>
                <p className="mt-1.5 text-xs text-text-muted">
                  {c.status === 'draft' ? `${toPersianDigits(c.parts)} پیامک برای هر نفر` : `${formatNumber(c.sent)} از ${formatNumber(c.recipients)} ارسال شد${c.failed ? ` • ${formatNumber(c.failed)} ناموفق` : ''}`}
                  {c.scheduled_at ? ` • زمان ارسال: ${formatJalaliDateTime(c.scheduled_at, timezone)}` : ''}
                </p>
              </div>
              {c.status === 'draft' || c.status === 'scheduled' ? <Button variant="secondary" onClick={() => setEditing(c)}>ویرایش و ارسال</Button> : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function CampaignEditor({ data, timezone, connected, initial, onDone }: { data: Data; timezone: string; connected: boolean; initial: SmsCampaignView | null; onDone: () => void }) {
  const [name, setName] = useState(initial?.name ?? '');
  const [body, setBody] = useState(initial?.body ?? '');
  const [aud, setAud] = useState<SmsAudience>(initial?.audience ?? {});
  const [count, setCount] = useState<number | null>(null);
  const [when, setWhen] = useState<'now' | 'later'>('now');
  const [date, setDate] = useState(addDays(todayIn(timezone), 1));
  const [time, setTime] = useState('10:00');
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const full = `${body}\n${data.rules.footer}`;

  useEffect(() => {
    let live = true;
    const t = setTimeout(async () => { const n = await countAudience(aud); if (live) setCount(n); }, 250);

    return () => { live = false; clearTimeout(t); };
  }, [aud]);

  const save = async (): Promise<string | null> => {
    const r = await saveSmsCampaign(initial?.id ?? null, { name, body, audience: aud });
    if (!r.ok) { setError(r.message); return null; }

    return r.data.id;
  };

  return (
    <Card>
      <CardHeader title={initial ? `ویرایش «${initial.name}»` : 'کمپین تازه'} />
      <div className="grid gap-6 p-5 lg:grid-cols-[3fr_2fr]">
        <div className="flex flex-col gap-4">
          <TextField label="نام کمپین (فقط برای خودتان)" value={name} maxLength={80} onChange={(e) => setName(e.target.value)} />
          <TextAreaField label="متن پیام" rows={4} maxLength={600} value={body} onChange={(e) => setBody(e.target.value)}
            hint={`${toPersianDigits([...full].length)} نویسه با «${data.rules.footer}» • ${toPersianDigits(smsParts(full))} پیامک برای هر نفر`} />
          <fieldset className="grid gap-3 sm:grid-cols-2">
            <legend className="mb-2 text-sm font-medium">چه کسانی؟ (فقط مشتریانی که اجازه داده‌اند)</legend>
            <SelectField label="سطح باشگاه" value={aud.tier_id ?? ''} onChange={(e) => setAud((a) => ({ ...a, tier_id: e.target.value || null }))}>
              <option value="">همه‌ی سطح‌ها</option>
              {data.tiers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </SelectField>
            <SelectField label="متولدان ماه" value={aud.birth_month ?? ''} onChange={(e) => setAud((a) => ({ ...a, birth_month: e.target.value ? Number(e.target.value) : null }))}>
              <option value="">همه‌ی ماه‌ها</option>
              {JALALI_MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
            </SelectField>
            <SelectField label="مشتریانی که نیامده‌اند" value={aud.inactive_days ?? ''} onChange={(e) => setAud((a) => ({ ...a, inactive_days: e.target.value ? Number(e.target.value) : null }))}>
              <option value="">مهم نیست</option>
              {[14, 30, 60, 90].map((d) => <option key={d} value={d}>{toPersianDigits(d)} روز یا بیشتر</option>)}
            </SelectField>
            <div className="flex items-end pb-2"><Checkbox label="فقط کسانی که قبلاً خرید کرده‌اند" checked={Boolean(aud.has_ordered)} onChange={(e) => setAud((a) => ({ ...a, has_ordered: e.target.checked }))} /></div>
          </fieldset>
          <fieldset className="flex flex-col gap-3">
            <legend className="mb-2 text-sm font-medium">زمان ارسال</legend>
            <div className="flex gap-2">
              {(['now', 'later'] as const).map((w) => (
                <button key={w} type="button" aria-pressed={when === w} onClick={() => setWhen(w)} className={cx('h-10 rounded-lg border px-4 text-sm', when === w ? 'border-brand bg-brand text-on-brand' : 'border-border-strong')}>{w === 'now' ? 'همین حالا' : 'زمان‌بندی'}</button>
              ))}
            </div>
            {when === 'later' ? (
              <div className="flex flex-wrap items-end gap-4">
                <JalaliDateField label="روز" name="date" defaultValue={date} future years={2} onChange={setDate} />
                <label className="flex flex-col gap-1.5 text-sm font-medium">ساعت<span className="inline-flex h-10 items-center rounded-md border border-border-strong px-2"><ClockSelect label="ساعت ارسال" value={time} onChange={setTime} minuteStep={15} /></span></label>
              </div>
            ) : null}
          </fieldset>
          {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
          <div className="flex flex-wrap gap-2">
            <Button icon={<Send />} loading={pending} disabled={!connected || !name.trim() || !body.trim() || count === 0}
              onClick={() => start(async () => {
                const id = await save();
                if (!id) return;
                const r = await campaignCommand(id, 'schedule', when === 'later' ? localToIso(date, time, timezone) : null);
                if (r.ok) onDone(); else setError(r.message);
              })}>{when === 'now' ? 'ارسال' : 'زمان‌بندی ارسال'}</Button>
            <Button variant="secondary" loading={pending} disabled={!name.trim() || !body.trim()} onClick={() => start(async () => { if (await save()) onDone(); })}>ذخیره‌ی پیش‌نویس</Button>
            {initial?.status === 'scheduled' ? <Button variant="ghost" icon={<X />} onClick={() => start(async () => { const r = await campaignCommand(initial.id, 'cancel'); if (r.ok) onDone(); else setError(r.message); })}>لغو ارسال</Button> : null}
            <Button variant="ghost" onClick={onDone}>بستن</Button>
          </div>
          {!connected ? <p className="text-xs text-warning">برای ارسال، اول پنل پیامک کافه را وصل کنید.</p> : null}
        </div>
        <div className="flex flex-col gap-3">
          <div className="rounded-2xl border border-border bg-surface-muted p-4">
            <p className="text-sm text-text-muted">گیرندگان</p>
            <p className="text-3xl font-black">{count === null ? '…' : formatNumber(count)}</p>
            <p className="text-xs text-text-muted">{count ? `${formatNumber(count * smsParts(full))} پیامک از اعتبار پنل شما` : 'کسی با این فیلتر اجازه نداده است'}</p>
          </div>
          <p className="text-xs font-semibold text-text-muted">پیش‌نمایش</p>
          <div className="max-w-sm whitespace-pre-line rounded-2xl rounded-ss-sm bg-surface-muted p-4 text-sm leading-7">{full}</div>
        </div>
      </div>
    </Card>
  );
}

function Log({ timezone }: { timezone: string }) {
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<SmsLogRow[] | null>(null);
  const [last, setLast] = useState(1);

  useEffect(() => {
    let live = true;
    void loadSmsLogs(page, status).then((r) => { if (live && r) { setRows(r.data); setLast(r.meta.last_page); } });

    return () => { live = false; };
  }, [page, status]);

  return (
    <Card>
      <div className="flex flex-wrap items-center justify-between gap-3 p-4">
        <p className="flex items-center gap-2 font-bold"><MessageSquareText className="size-5 text-brand" aria-hidden="true" />پیامک‌های ارسالی</p>
        <div className="flex gap-1 rounded-lg bg-surface-muted p-0.5 text-sm">
          {[['', 'همه'], ['sent', 'موفق'], ['failed', 'ناموفق'], ['skipped', 'ارسال‌نشده']].map(([k, l]) => (
            <button key={k} type="button" onClick={() => { setStatus(k); setPage(1); }} className={cx('rounded-md px-3 py-1', status === k ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>{l}</button>
          ))}
        </div>
      </div>
      {rows === null ? <p className="p-6 text-center text-sm text-text-muted">در حال بارگذاری…</p> : rows.length === 0 ? (
        <EmptyState icon={<MessageSquareText />} title="هنوز پیامکی ثبت نشده" />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[40rem] text-sm">
            <thead className="bg-surface-muted/60 text-xs text-text-muted"><tr>
              <th className="px-4 py-2 text-start font-medium">زمان</th><th className="px-4 py-2 text-start font-medium">نوع</th><th className="px-4 py-2 text-start font-medium">گیرنده</th>
              <th className="px-4 py-2 text-start font-medium">متن</th><th className="px-4 py-2 text-start font-medium">وضعیت</th>
            </tr></thead>
            <tbody className="divide-y divide-border">
              {rows.map((r) => (
                <tr key={r.id}>
                  <td className="whitespace-nowrap px-4 py-2.5 text-text-muted">{formatJalaliDateTime(r.created_at, timezone)}</td>
                  <td className="px-4 py-2.5">{SMS_KIND_LABELS[r.kind] ?? r.kind}</td>
                  <td className="px-4 py-2.5" dir="ltr">{toPersianDigits(r.recipient)}</td>
                  <td className="max-w-xs px-4 py-2.5"><span className="line-clamp-1">{r.body}</span></td>
                  <td className="px-4 py-2.5">
                    <Badge tone={r.status === 'sent' ? 'success' : r.status === 'failed' ? 'danger' : 'warning'} dot>{r.status === 'sent' ? 'ارسال شد' : r.status === 'failed' ? 'ناموفق' : 'ارسال نشد'}</Badge>
                    {r.error ? <span className="ms-2 text-xs text-text-muted">{SMS_ERRORS[r.error] ?? r.error}</span> : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {last > 1 ? (
        <div className="flex items-center justify-center gap-3 border-t border-border p-3 text-sm">
          <Button size="sm" variant="ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>قبلی</Button>
          <span className="text-text-muted">{toPersianDigits(page)} از {toPersianDigits(last)}</span>
          <Button size="sm" variant="ghost" disabled={page >= last} onClick={() => setPage((p) => p + 1)}>بعدی</Button>
        </div>
      ) : null}
    </Card>
  );
}
