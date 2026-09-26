'use client';

import { Shuffle } from 'lucide-react';
import { Button, Card, CardHeader, cx } from '@cafe/ui';
import { DESIGN_LABELS, SECTION_LABELS, type LandingContent, type LandingDesign, type LandingHero, type LandingTemplate } from '@/lib/landing-types';
import type { Draft } from './LandingEditor';

const pick = <T,>(values: readonly T[]): T => values[Math.floor(Math.random() * values.length)];
const keysOf = <T extends object>(o: T) => Object.keys(o) as (keyof T)[];

/** A fresh random look and section layouts (the words stay), so cafés don't all end up alike. */
export function shuffledDesign(draft: Draft): Pick<Draft, 'design' | 'content'> {
  const design = { ...draft.design };
  for (const key of keysOf(DESIGN_LABELS)) {
    const options = Object.keys(DESIGN_LABELS[key].options);
    (design as Record<string, string>)[key] = pick(key === 'motion' ? options.filter((o) => o !== 'none') : options);
  }
  const content = structuredClone(draft.content) as LandingContent;
  for (const section of keysOf(SECTION_LABELS)) {
    (content[section] as { variant: string }).variant = pick(Object.keys(SECTION_LABELS[section].variants));
  }

  return { design, content };
}

/** Segmented buttons for one look option. */
function Choice<K extends keyof LandingDesign>({ name, value, onChange }: { name: K; value: LandingDesign[K]; onChange: (v: LandingDesign[K]) => void }) {
  const { title, options } = DESIGN_LABELS[name];

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-2 text-sm font-semibold">{title}</legend>
      <div className="flex flex-wrap gap-2">
        {(Object.entries(options) as [LandingDesign[K], string][]).map(([key, label]) => (
          <button key={key} type="button" aria-pressed={value === key} onClick={() => onChange(key)}
            className={cx('h-9 rounded-full border px-4 text-sm transition-colors', value === key ? 'border-brand bg-brand-soft font-semibold text-text' : 'border-border text-text-muted hover:border-border-strong hover:text-text')}>
            {label}
          </button>
        ))}
      </div>
    </fieldset>
  );
}

/** A tiny swatch drawn with the template's real tokens. */
function TemplateSwatch({ template }: { template: LandingTemplate }) {
  return (
    <span className={cx('landing landing-preview l-card-sm flex h-16 w-full flex-col justify-end gap-1 overflow-hidden border border-border p-2', template === 'bold' && 'bg-brand')}
      data-template={template} data-theme={template === 'night' ? 'dark' : undefined}>
      <span className={cx('h-2 w-2/3 rounded-full', template === 'bold' ? 'bg-on-brand' : 'bg-text/70')} />
      <span className={cx('h-1.5 w-1/2 rounded-full', template === 'bold' ? 'bg-on-brand/60' : 'bg-text-subtle/50')} />
      <span className={cx('mt-1 h-3 w-8 rounded-full', template === 'bold' ? 'bg-on-brand' : 'bg-brand')} />
    </span>
  );
}

/** Four little layout drawings for the hero choice. */
function HeroSketch({ hero }: { hero: LandingHero }) {
  const bar = 'rounded-full bg-text-muted/60';
  const photo = 'rounded-md bg-brand/35';

  return (
    <span aria-hidden="true" className="relative flex h-16 w-full overflow-hidden rounded-lg border border-border bg-surface-muted p-1.5">
      {hero === 'cover' ? (<><span className={cx(photo, 'absolute inset-0 rounded-none')} /><span className="relative mt-auto flex w-full flex-col gap-1"><span className={cx(bar, 'h-1.5 w-2/3 bg-on-media')} /><span className={cx(bar, 'h-1 w-1/2 bg-on-media/70')} /></span></>) : null}
      {hero === 'center' ? (<><span className={cx(photo, 'absolute inset-0 rounded-none')} /><span className="relative m-auto flex w-full flex-col items-center gap-1"><span className={cx(bar, 'h-1.5 w-2/3 bg-on-media')} /><span className={cx(bar, 'h-1 w-1/3 bg-on-media/70')} /></span></>) : null}
      {hero === 'split' ? (<span className="flex w-full gap-1.5"><span className="flex flex-1 flex-col justify-center gap-1"><span className={cx(bar, 'h-1.5 w-full')} /><span className={cx(bar, 'h-1 w-2/3')} /></span><span className={cx(photo, 'w-2/5')} /></span>) : null}
      {hero === 'poster' ? (<span className="flex w-full flex-col items-center gap-1"><span className="h-2 w-full rounded-sm border border-text-muted/50" /><span className={cx(photo, 'h-8 w-6 rounded-t-full')} /><span className={cx(bar, 'h-1 w-1/2')} /></span>) : null}
    </span>
  );
}

export function DesignPanel({ design, onChange, onShuffle }: { design: LandingDesign; onChange: (d: LandingDesign) => void; onShuffle: () => void }) {
  const set = <K extends keyof LandingDesign>(key: K) => (value: LandingDesign[K]) => onChange({ ...design, [key]: value });

  return (
    <Card className="flex flex-col gap-6 p-5">
      <CardHeader title="ظاهر صفحه" description="هر ترکیب، یک کافه‌ی دیگر: حال‌وهوا، چیدمان سردر و جزئیات را عوض کنید و نتیجه را همان کنار ببینید." />
      <Button variant="secondary" onClick={onShuffle} icon={<Shuffle className="size-4" />}>یک ترکیب تازه پیشنهاد بده</Button>

      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{DESIGN_LABELS.template.title}</legend>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {(Object.entries(DESIGN_LABELS.template.options) as [LandingTemplate, string][]).map(([key, label]) => (
            <button key={key} type="button" aria-pressed={design.template === key} onClick={() => set('template')(key)}
              className={cx('flex flex-col gap-2 rounded-2xl border p-2 text-sm transition-colors', design.template === key ? 'border-brand bg-brand-soft font-semibold' : 'border-border hover:border-border-strong')}>
              <TemplateSwatch template={key} />{label}
            </button>
          ))}
        </div>
      </fieldset>

      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{DESIGN_LABELS.hero.title}</legend>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {(Object.entries(DESIGN_LABELS.hero.options) as [LandingHero, string][]).map(([key, label]) => (
            <button key={key} type="button" aria-pressed={design.hero === key} onClick={() => set('hero')(key)}
              className={cx('flex flex-col gap-2 rounded-2xl border p-2 text-sm transition-colors', design.hero === key ? 'border-brand bg-brand-soft font-semibold' : 'border-border hover:border-border-strong')}>
              <HeroSketch hero={key} />{label}
            </button>
          ))}
        </div>
      </fieldset>

      <div className="grid gap-5 sm:grid-cols-2">
        <Choice name="font" value={design.font} onChange={set('font')} />
        <Choice name="type" value={design.type} onChange={set('type')} />
        <Choice name="texture" value={design.texture} onChange={set('texture')} />
        <Choice name="corners" value={design.corners} onChange={set('corners')} />
        <Choice name="motion" value={design.motion} onChange={set('motion')} />
      </div>
      <p className="text-xs leading-6 text-text-subtle">رنگ اصلی صفحه همان رنگ برند کافه است (تنظیمات ← برند). «پررنگ» زمینه‌ی بعضی بخش‌ها را هم به همین رنگ درمی‌آورد.</p>
    </Card>
  );
}
