import Link from 'next/link';
import { AlertTriangle, ArrowLeft, ArrowRight, BookOpen, ChevronLeft, ExternalLink, Info, Lightbulb, Lock } from 'lucide-react';
import { cx } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import type { HelpTopic } from '@/lib/help';
import { HelpIcon } from './HelpIcon';

/**
 * One help topic: summary, numbered sections with steps, worked examples, tips and warnings,
 * FAQs, related topics and previous/next. `base` is /dashboard/help or /platform/help.
 */
export function HelpArticle({ topic, base, related, prev, next, canOpenScreen, locked }: {
  topic: HelpTopic;
  base: string;
  related: HelpTopic[];
  prev: HelpTopic | null;
  next: HelpTopic | null;
  canOpenScreen: boolean;
  locked: boolean;
}) {
  return (
    <article className="flex flex-col gap-6">
      <nav aria-label="مسیر" className="flex items-center gap-1 text-sm text-text-muted">
        <Link href={base} className="inline-flex items-center gap-1 hover:text-text"><BookOpen className="size-4" aria-hidden="true" />راهنما</Link>
        <ChevronLeft className="size-4" aria-hidden="true" />
        <span className="truncate text-text">{topic.title}</span>
      </nav>

      <header className="relative overflow-hidden rounded-3xl border border-border bg-surface p-5 shadow-[var(--shadow-sm)] sm:p-7">
        <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(ellipse_70%_90%_at_100%_0%,var(--color-brand-soft),transparent_70%)]" />
        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand text-on-brand shadow-[var(--shadow-md)]"><HelpIcon name={topic.icon} className="size-7" /></span>
          <div className="min-w-0 flex-1">
            <h1 className="text-2xl font-black leading-tight sm:text-3xl">{topic.title}</h1>
            <p className="mt-2 leading-8 text-text-muted">{topic.summary}</p>
            <div className="mt-4 flex flex-wrap items-center gap-2">
              {topic.screen && canOpenScreen && !locked ? (
                <Link href={topic.screen} className="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-strong">
                  <ExternalLink className="size-4" aria-hidden="true" />رفتن به همین بخش
                </Link>
              ) : null}
              {locked ? <span className="inline-flex items-center gap-1.5 rounded-xl bg-warning-soft px-3 py-2 text-sm text-warning"><Lock className="size-4" aria-hidden="true" />این بخش در پلن فعلی شما فعال نیست</span> : null}
            </div>
          </div>
        </div>
      </header>

      <div className="grid items-start gap-6 lg:grid-cols-[1fr_15rem]">
        <div className="flex min-w-0 flex-col gap-5">
          {topic.sections.map((s, i) => (
            <section key={s.title} id={`s-${i + 1}`} aria-labelledby={`h-${i + 1}`} className="scroll-mt-24 rounded-3xl border border-border bg-surface p-5 shadow-[var(--shadow-sm)] sm:p-6">
              <h2 id={`h-${i + 1}`} className="flex items-center gap-3 text-lg font-black">
                <span aria-hidden="true" className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-sm text-brand">{toPersianDigits(i + 1)}</span>
                {s.title}
              </h2>
              <div className="mt-4 flex flex-col gap-4 leading-8">
                {s.body?.map((p) => <p key={p} className="text-text-muted">{p}</p>)}
                {s.steps ? (
                  <ol className="flex flex-col gap-3">
                    {s.steps.map((step, n) => (
                      <li key={step} className="flex gap-3">
                        <span aria-hidden="true" className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full border-2 border-brand text-xs font-bold text-brand">{toPersianDigits(n + 1)}</span>
                        <span>{step}</span>
                      </li>
                    ))}
                  </ol>
                ) : null}
                {s.points ? (
                  <ul className="flex flex-col gap-2">
                    {s.points.map((pt) => <li key={pt} className="flex gap-3"><span aria-hidden="true" className="mt-3 size-1.5 shrink-0 rounded-full bg-brand" /><span>{pt}</span></li>)}
                  </ul>
                ) : null}
                {s.example ? (
                  <div className="rounded-2xl border border-accent/30 bg-accent-soft p-4">
                    <p className="mb-1 flex items-center gap-2 text-sm font-bold text-accent"><Lightbulb className="size-4" aria-hidden="true" />{s.example.title}</p>
                    <p className="text-sm leading-7 text-text">{s.example.text}</p>
                  </div>
                ) : null}
                {s.tip ? <Note tone="info" icon={<Info className="size-4" aria-hidden="true" />} label="نکته">{s.tip}</Note> : null}
                {s.warning ? <Note tone="warning" icon={<AlertTriangle className="size-4" aria-hidden="true" />} label="توجه">{s.warning}</Note> : null}
              </div>
            </section>
          ))}

          {topic.faq?.length ? (
            <section aria-labelledby="faq" className="rounded-3xl border border-border bg-surface p-5 shadow-[var(--shadow-sm)] sm:p-6">
              <h2 id="faq" className="mb-3 text-lg font-black">پرسش‌های پرتکرار</h2>
              <div className="flex flex-col divide-y divide-border">
                {topic.faq.map((f) => (
                  <details key={f.q} className="group py-3">
                    <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold [&::-webkit-details-marker]:hidden">
                      {f.q}<ChevronLeft className="size-4 shrink-0 text-text-muted transition-transform group-open:-rotate-90" aria-hidden="true" />
                    </summary>
                    <p className="mt-2 leading-8 text-text-muted">{f.a}</p>
                  </details>
                ))}
              </div>
            </section>
          ) : null}

          {related.length ? (
            <section aria-labelledby="related" className="flex flex-col gap-3">
              <h2 id="related" className="text-lg font-black">مطالب مرتبط</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {related.map((r) => (
                  <Link key={r.key} href={`${base}/${r.key}`} className="flex items-center gap-3 rounded-2xl border border-border bg-surface p-3 shadow-[var(--shadow-sm)] transition-colors hover:border-brand">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand"><HelpIcon name={r.icon} className="size-5" /></span>
                    <span className="min-w-0"><span className="block truncate font-semibold">{r.title}</span><span className="line-clamp-1 text-xs text-text-muted">{r.summary}</span></span>
                  </Link>
                ))}
              </div>
            </section>
          ) : null}

          <nav aria-label="موضوع قبلی و بعدی" className="grid gap-3 sm:grid-cols-2">
            {prev ? (
              <Link href={`${base}/${prev.key}`} className="flex items-center gap-2 rounded-2xl border border-border bg-surface p-4 text-sm hover:border-brand">
                <ArrowRight className="size-4 shrink-0 text-text-muted" aria-hidden="true" /><span><span className="block text-xs text-text-muted">قبلی</span>{prev.title}</span>
              </Link>
            ) : <span />}
            {next ? (
              <Link href={`${base}/${next.key}`} className="flex items-center justify-end gap-2 rounded-2xl border border-border bg-surface p-4 text-end text-sm hover:border-brand">
                <span><span className="block text-xs text-text-muted">بعدی</span>{next.title}</span><ArrowLeft className="size-4 shrink-0 text-text-muted" aria-hidden="true" />
              </Link>
            ) : null}
          </nav>
        </div>

        <aside aria-label="فهرست این صفحه" className="sticky top-24 hidden flex-col gap-1 rounded-2xl border border-border bg-surface p-3 text-sm lg:flex">
          <p className="px-2 pb-1 text-xs font-semibold text-text-muted">در این صفحه</p>
          {topic.sections.map((s, i) => (
            <a key={s.title} href={`#s-${i + 1}`} className="rounded-lg px-2 py-1.5 text-text-muted hover:bg-surface-muted hover:text-text">{toPersianDigits(i + 1)}. {s.title}</a>
          ))}
          {topic.faq?.length ? <a href="#faq" className="rounded-lg px-2 py-1.5 text-text-muted hover:bg-surface-muted hover:text-text">پرسش‌های پرتکرار</a> : null}
        </aside>
      </div>
    </article>
  );
}

function Note({ tone, icon, label, children }: { tone: 'info' | 'warning'; icon: React.ReactNode; label: string; children: React.ReactNode }) {
  return (
    <p className={cx('flex gap-2 rounded-2xl px-4 py-3 text-sm leading-7', tone === 'info' ? 'bg-info-soft text-info' : 'bg-warning-soft text-warning')}>
      <span className="mt-1.5 shrink-0">{icon}</span><span><b>{label}: </b>{children}</span>
    </p>
  );
}
