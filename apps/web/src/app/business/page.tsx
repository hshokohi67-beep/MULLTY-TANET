import type { Metadata } from 'next';
import Link from 'next/link';
import type { ComponentType, CSSProperties, ReactNode } from 'react';
import {
  ArrowLeft, BellRing, BookOpenCheck, Building2, CalendarClock, ChartColumn, ChefHat, Coffee, Compass, Crown, Globe2, Languages, MessageSquareText,
  PackageSearch, QrCode, Rocket, ShieldCheck, Smartphone, Sparkles, Store, UtensilsCrossed, type LucideIcon,
} from 'lucide-react';
import { cx } from '@cafe/ui';
import { ThemeSwitch } from '@/components/ThemeSwitch';
import { LandingFrame } from '@/components/store/landing/LandingFrame';
import { api } from '@/lib/api';
import type { LandingDesign } from '@/lib/landing-types';
import { ClubAndSms, KitchenAndPush, MarketplaceMock, OrderBoard, PhoneMenu, ReportMock, StockAndProfit } from './Mocks';
import { Pricing, type PlanFeature, type PublicPlan } from './Pricing';

export const metadata: Metadata = {
  title: { absolute: 'کافه‌یار برای کسب‌وکارها: منو، سفارش و مدیریت کافه و رستوران' },
  description: 'منوی آنلاین و سایت اختصاصی، سفارش از میز و پیک، صندوق و آشپزخانه، باشگاه مشتریان، پیامک، انبار و گزارش؛ همه در یک پنل فارسی. ۱۴ روز رایگان.',
  openGraph: { title: 'کافه‌یار برای کسب‌وکارها', description: 'همه‌ی کافه یا رستوران‌تان در یک پنل فارسی. ۱۴ روز رایگان.' },
};

// The page reuses the landing-page frame for its scroll reveals, textures and type.
const LOOK: LandingDesign = { template: 'bright', font: 'vazirmatn', type: 'bold', hero: 'cover', texture: 'glow', corners: 'round', motion: 'lively' };

interface Spotlight { id: string; icon: LucideIcon; eyebrow: string; title: string; text: string; points: string[]; visual: ReactNode }

const SPOTLIGHTS: Spotlight[] = [
  {
    id: 'menu', icon: UtensilsCrossed, eyebrow: 'منو و سایت اختصاصی', title: 'منویی که خودش می‌فروشد',
    text: 'هر کافه آدرس خودش را دارد و یک صفحه‌ی معرفی با عکس و ویدیو؛ منو با عکس، کالری، برچسب «پرفروش» و فیلتر، روی هر گوشی سریع باز می‌شود.',
    points: ['آدرس اختصاصی مثل naranj.cafeyar.ir', 'صفحه‌ی معرفی با ۴ حال‌وهوا و ده‌ها ترکیب', 'کالری، فیلتر رژیمی و «کنارش می‌چسبد»', 'استوری برای محصول تازه و تخفیف امروز'],
    visual: <PhoneMenu className="mx-auto rotate-[-3deg]" />,
  },
  {
    id: 'orders', icon: QrCode, eyebrow: 'سفارش از میز، بیرون‌بر و پیک', title: 'همه‌ی سفارش‌ها در یک تابلو',
    text: 'مشتری QR روی میز را اسکن می‌کند و بدون ثبت‌نام سفارش می‌دهد؛ سفارش‌های بیرون‌بر و پیک هم همان‌جا می‌رسند و هر وضعیت برای مشتری زنده دیده می‌شود.',
    points: ['QR برای هر میز؛ «صدا زدن گارسون» و «صورت‌حساب»', 'محدوده‌های ارسال روی نقشه، با ارسال رایگان', 'پیش‌سفارش برای ساعت دلخواه مشتری', 'پرداخت آنلاین مستقیم به حساب خود کافه'],
    visual: <OrderBoard />,
  },
  {
    id: 'kitchen', icon: ChefHat, eyebrow: 'آشپزخانه و اعلان', title: 'آشپزخانه‌ای که چیزی را جا نمی‌اندازد',
    text: 'هر ایستگاه (بار، آشپزخانه، شیرینی) فقط سهم خودش را روی تبلت می‌بیند، با زمان انتظار. سفارش تازه روی گوشی صندوق‌دار هم اعلان می‌شود، حتی وقتی پنل بسته است.',
    points: ['نمایشگر آشپزخانه با ایستگاه‌های جدا', 'اعلان سفارش تازه روی گوشی و کامپیوتر', 'مشتری هم «سفارشت آماده است» را می‌گیرد', 'تحویل خودکار سفارش‌های سالن'],
    visual: <KitchenAndPush />,
  },
  {
    id: 'club', icon: Crown, eyebrow: 'باشگاه مشتریان و پیامک', title: 'مشتری یک‌بار، مشتری همیشه',
    text: 'سطح‌های باشگاه، کش‌بک و امتیاز، کیف پول و هدیه‌ی تولد، و پیامک از خط خود کافه: خودکار برای «آماده شد» و کمپین هدفمند برای کسانی که اجازه داده‌اند.',
    points: ['سطح‌ها، کش‌بک، امتیاز و کیف پول', 'هدیه‌ی تولد و پاداش معرفی دوستان', 'اتصال به پنل پیامک خودتان (۵ سرویس ایرانی)', 'کمپین با رعایت ساعت سکوت و «لغو۱۱»'],
    visual: <ClubAndSms />,
  },
  {
    id: 'stock', icon: PackageSearch, eyebrow: 'انبار و بهای تمام‌شده', title: 'سود واقعی هر فنجان را بدانید',
    text: 'رسپی هر محصول را یک‌بار تعریف کنید؛ با هر فروش مواد اولیه خودکار کم می‌شود، کمبود قبل از تمام شدن خبر می‌دهد و سود واقعی هر محصول معلوم است.',
    points: ['مواد اولیه، رسپی و کسر خودکار با فروش', 'خرید، تأمین‌کننده و بدهی', 'ضایعات و انبارگردانی', 'بهای تمام‌شده و سود هر محصول'],
    visual: <StockAndProfit />,
  },
  {
    id: 'reports', icon: ChartColumn, eyebrow: 'گزارش و پیشخوان', title: 'عددها، قبل از اینکه دیر شود',
    text: 'پیشخوانی که خودتان می‌چینید، گزارش فروش به ساعت و محصول و شعبه، و خلاصه‌ی پایان روز با پیامک؛ خروجی اکسل و PDF با تاریخ شمسی.',
    points: ['پیشخوان قابل چینش برای هر نقش', 'فروش ساعتی، محصولات، شعبه‌ها و مشتریان', 'سود و زیان با حقوق و هزینه‌ها', 'خروجی اکسل، CSV و PDF'],
    visual: <ReportMock />,
  },
];

const MORE: { icon: LucideIcon; title: string; text: string }[] = [
  { icon: CalendarClock, title: 'کارکنان و حقوق', text: 'شیفت، ساعت‌زنی، حضور و محاسبه‌ی حقوق از روی ساعت کار.' },
  { icon: Building2, title: 'چند شعبه', text: 'قیمت، موجودی و ساعت کاری جدا برای هر شعبه، گزارش یکجا.' },
  { icon: Compass, title: 'خوراک‌گردی', text: 'معرفی رایگان به مشتری‌های شهر؛ تبلیغ بنر و جایگاه بالای نتایج.' },
  { icon: ShieldCheck, title: 'نقش و دسترسی', text: 'صندوق‌دار، آشپزخانه، سالن‌دار و مدیر؛ هر کس فقط کار خودش.' },
  { icon: BookOpenCheck, title: 'راهنمای کامل فارسی', text: 'برای هر صفحه و هر نقش، قدم‌به‌قدم و با مثال.' },
  { icon: Languages, title: 'ساخته‌شده برای ایران', text: 'تاریخ شمسی، تومان، پیامک و درگاه ایرانی، راست‌به‌چپ کامل.' },
];

const STEPS = [
  { icon: Rocket, title: 'ثبت‌نام در دو دقیقه', text: 'نام کافه، موبایل و یک رمز. آدرس اختصاصی همان لحظه ساخته می‌شود.' },
  { icon: Coffee, title: 'منو و میزها', text: 'محصولات، قیمت‌ها و میزها را وارد کنید؛ راهنمای راه‌اندازی کنارتان است.' },
  { icon: Smartphone, title: 'QR روی میز، لینک در اینستاگرام', text: 'از همان روز سفارش بگیرید؛ ۱۴ روز رایگان، بعد پلن را انتخاب کنید.' },
];

const FAQ = [
  ['نصب یا دستگاه خاصی لازم است؟', 'نه. پنل روی مرورگر گوشی، تبلت و کامپیوتر کار می‌کند. برای آشپزخانه هر تبلت ارزان‌قیمتی کافی است.'],
  ['پول سفارش‌های آنلاین به حساب چه کسی می‌رود؟', 'مستقیم به حساب درگاه خود کافه (زرین‌پال). کافه‌یار واسطه‌ی پول شما نیست.'],
  ['پیامک‌ها از کجا ارسال می‌شود؟', 'کد ورود مشتری‌ها را کافه‌یار می‌فرستد. پیام‌های کافه (آماده شدن سفارش، تولد، کمپین) از پنل پیامک خود کافه ارسال می‌شود تا هزینه و مسئولیت دست خودتان باشد.'],
  ['بعد از ۱۴ روز چه می‌شود؟', 'پلن دلخواه را انتخاب و پرداخت می‌کنید. اگر نکنید، اطلاعات پاک نمی‌شود و پنل فقط‌خواندنی می‌ماند تا هر وقت خواستید ادامه دهید.'],
  ['چند شعبه دارم؛ جواب می‌دهد؟', 'بله. هر شعبه منو، قیمت، موجودی، ساعت کاری و محدوده‌ی ارسال خودش را دارد و گزارش‌ها کنار هم دیده می‌شوند.'],
  ['اطلاعاتم امن است؟', 'اطلاعات هر کافه کاملاً جداست، رمزها و کلیدها رمزنگاری می‌شوند و نسخه‌ی پشتیبان منظم گرفته می‌شود.'],
];

async function loadPlans(): Promise<{ plans: PublicPlan[]; features: PlanFeature[]; vat_rate: number; trial_days: number } | null> {
  try {
    return (await api<{ data: { plans: PublicPlan[]; features: PlanFeature[]; vat_rate: number; trial_days: number } }>('/public/plans', { auth: false, tenant: false, revalidate: 300 })).data;
  } catch {
    return null;
  }
}

function Cta({ className, big = false }: { className?: string; big?: boolean }) {
  return (
    <Link href="/signup" className={cx('l-pill inline-flex items-center gap-2 bg-brand font-bold text-on-brand shadow-[var(--shadow-md)] transition-transform hover:-translate-y-0.5 hover:bg-brand-strong', big ? 'h-14 px-8 text-lg' : 'h-12 px-6', className)}>
      شروع رایگان ۱۴ روزه<ArrowLeft className="size-4" aria-hidden="true" />
    </Link>
  );
}

function Icon({ icon: I, className }: { icon: ComponentType<{ className?: string }>; className?: string }) {
  return <span className={cx('flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand', className)}><I className="size-5" /></span>;
}

/**
 * «کافه‌یار برای کسب‌وکارها»: what the platform does, for café and restaurant owners, with the
 * plans and a way in (/signup). Everything listed exists in the product today.
 */
export default async function BusinessPage() {
  const pricing = await loadPlans();

  return (
    <LandingFrame design={LOOK} brandCss={null} className="flex min-h-dvh flex-col">
      <header className="glass sticky top-0 z-30">
        <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
          <Link href="/business" className="flex items-center gap-2.5">
            <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-on-brand"><Coffee className="size-5" aria-hidden="true" /></span>
            <span className="font-black">کافه‌یار</span>
          </Link>
          <nav aria-label="بخش‌ها" className="ms-4 hidden items-center gap-5 text-sm text-text-muted md:flex">
            <a href="#features" className="hover:text-text">امکانات</a>
            <a href="#pricing" className="hover:text-text">قیمت‌ها</a>
            <a href="#faq" className="hover:text-text">سؤال‌ها</a>
            <Link href="/explore" className="hover:text-text">خوراک‌گردی</Link>
          </nav>
          <div className="ms-auto flex items-center gap-2">
            <ThemeSwitch />
            <Link href="/login" className="hidden h-10 items-center rounded-xl px-3 text-sm text-text-muted hover:text-text sm:inline-flex">ورود</Link>
            <Link href="/signup" className="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-bold text-on-brand hover:bg-brand-strong">شروع رایگان</Link>
          </div>
        </div>
      </header>

      <main id="main" className="flex-1">
        {/* Hero */}
        <section className="l-texture overflow-hidden">
          <div className="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-[1.1fr_1fr] lg:pb-28 lg:pt-20">
            <div className="flex flex-col gap-6">
              <span className="l-rise inline-flex w-fit items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1.5 text-sm font-semibold text-text" style={{ '--i': 0 } as CSSProperties}>
                <Store className="size-4 text-brand" aria-hidden="true" />برای کافه، رستوران، شیرینی‌فروشی و هر خوراکی‌فروش
              </span>
              <h1 className="l-display l-rise text-4xl leading-[1.25] sm:text-6xl" style={{ '--i': 1 } as CSSProperties}>
                کافه‌تان را <span className="text-brand">حرفه‌ای</span> بچرخانید؛ از منو تا سود خالص.
              </h1>
              <p className="l-rise max-w-xl text-lg leading-9 text-text-muted" style={{ '--i': 2 } as CSSProperties}>
                منوی آنلاین و سایت اختصاصی، سفارش از میز و پیک، آشپزخانه، باشگاه مشتریان، انبار و گزارش؛ همه در یک پنل فارسی، روی گوشی و کامپیوتر.
              </p>
              <div className="l-rise flex flex-wrap items-center gap-3" style={{ '--i': 3 } as CSSProperties}>
                <Cta big />
                <a href="#features" className="l-pill inline-flex h-14 items-center border border-border-strong px-6 font-semibold hover:border-brand hover:text-brand">امکانات را ببینید</a>
              </div>
              <p className="l-rise flex flex-wrap gap-x-4 gap-y-1 text-sm text-text-subtle" style={{ '--i': 4 } as CSSProperties}>
                <span>✓ بدون کارت بانکی</span><span>✓ بدون نصب</span><span>✓ پشتیبانی فارسی</span>
              </p>
            </div>
            <div className="relative flex justify-center" aria-hidden="true">
              <div className="absolute inset-x-6 top-10 -z-10 h-72 rounded-full bg-brand/20 blur-3xl" />
              <PhoneMenu className="float-y rotate-[4deg]" />
              <div className="glass absolute -end-4 top-44 hidden items-center gap-2 rounded-2xl p-2.5 pe-4 shadow-[var(--shadow-lg)] sm:flex">
                <span className="flex size-9 items-center justify-center rounded-xl bg-success-soft text-success"><BellRing className="size-4" /></span>
                <span className="text-xs"><span className="block font-bold">سفارش #۱۲ آماده است</span><span className="text-text-muted">کافه نارنج</span></span>
              </div>
              <div className="glass absolute -start-4 bottom-16 hidden items-center gap-2 rounded-2xl p-2.5 pe-4 shadow-[var(--shadow-lg)] sm:flex">
                <span className="flex size-9 items-center justify-center rounded-xl bg-accent-soft text-accent"><Sparkles className="size-4" /></span>
                <span className="text-xs"><span className="block font-bold">سفارش تازه از میز ۶</span><span className="text-text-muted">۲ لاته، ۱ چیزکیک</span></span>
              </div>
            </div>
          </div>
        </section>

        {/* What's inside, at a glance */}
        <section aria-label="در یک نگاه" className="border-y border-border bg-surface">
          <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-px px-5 sm:grid-cols-3 sm:px-8 lg:grid-cols-6">
            {[[QrCode, 'سفارش با QR'], [ChefHat, 'نمایشگر آشپزخانه'], [Crown, 'باشگاه و کیف پول'], [MessageSquareText, 'پیامک خودکار'], [PackageSearch, 'انبار و رسپی'], [Globe2, 'سایت اختصاصی']].map(([I, t], i) => {
              const Ico = I as LucideIcon;

              return (
                <li key={t as string} data-reveal style={{ '--i': i } as CSSProperties} className="flex items-center justify-center gap-2 py-5 text-sm font-semibold">
                  <Ico className="size-4 text-brand" aria-hidden="true" />{t as string}
                </li>
              );
            })}
          </ul>
        </section>

        {/* Spotlights */}
        <section id="features" className="scroll-mt-20">
          <div className="mx-auto max-w-6xl px-5 pt-20 text-center sm:px-8 lg:pt-28">
            <span data-reveal className="l-eyebrow text-brand">امکانات</span>
            <h2 data-reveal className="l-display mt-3 text-3xl sm:text-5xl">هرچه یک کافه لازم دارد، کنار هم</h2>
            <p data-reveal className="mx-auto mt-4 max-w-2xl text-lg leading-8 text-text-muted">به‌جای چند نرم‌افزار جدا و چند دفتر، یک پنل که با هم حرف می‌زنند: فروش، انبار را کم می‌کند؛ سفارش، پیامک مشتری را می‌فرستد؛ حقوق، به گزارش سود می‌رسد.</p>
          </div>

          {SPOTLIGHTS.map((s, i) => (
            <div key={s.id} id={s.id} className={cx('l-texture scroll-mt-20 overflow-hidden', i % 2 === 1 && 'bg-surface-muted/60')}>
              <div className="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:gap-20 lg:py-24">
                <div className={cx('flex flex-col gap-5', i % 2 === 1 && 'lg:order-last')}>
                  <span data-reveal className="flex items-center gap-3"><Icon icon={s.icon} /><span className="l-eyebrow text-brand">{s.eyebrow}</span></span>
                  <h3 data-reveal className="l-display text-3xl sm:text-4xl">{s.title}</h3>
                  <p data-reveal className="text-lg leading-8 text-text-muted">{s.text}</p>
                  <ul className="grid gap-2.5 sm:grid-cols-2">
                    {s.points.map((p, k) => (
                      <li key={p} data-reveal style={{ '--i': k } as CSSProperties} className="flex items-start gap-2 rounded-2xl bg-surface p-3 text-sm leading-6 shadow-[var(--shadow-sm)]">
                        <span className="mt-1 size-1.5 shrink-0 rounded-full bg-brand" aria-hidden="true" />{p}
                      </li>
                    ))}
                  </ul>
                </div>
                <div data-reveal aria-hidden="true" className="relative">
                  <div className="absolute inset-8 -z-10 rounded-full bg-brand/15 blur-3xl" />
                  {s.visual}
                </div>
              </div>
            </div>
          ))}

          {/* Discovery */}
          <div className="l-texture overflow-hidden">
            <div className="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:gap-20">
              <div className="flex flex-col gap-5">
                <span data-reveal className="flex items-center gap-3"><Icon icon={Compass} /><span className="l-eyebrow text-brand">خوراک‌گردی</span></span>
                <h3 data-reveal className="l-display text-3xl sm:text-4xl">مشتری تازه، از همان محله</h3>
                <p data-reveal className="text-lg leading-8 text-text-muted">«خوراک‌گردی» راهنمای کافه‌ها و خوراکی‌فروشی‌های هر شهر است. کافه‌تان را رایگان معرفی کنید؛ اگر خواستید، با تبلیغ بنر یا جایگاه بالای نتایج بیشتر دیده شوید.</p>
                <Link data-reveal href="/explore" className="inline-flex w-fit items-center gap-1.5 font-semibold text-brand hover:underline">خوراک‌گردی را ببینید<ArrowLeft className="size-4" aria-hidden="true" /></Link>
              </div>
              <div data-reveal aria-hidden="true" className="flex justify-center"><MarketplaceMock className="rotate-[-2deg]" /></div>
            </div>
          </div>

          {/* And more */}
          <div className="mx-auto max-w-6xl px-5 pb-20 sm:px-8">
            <h3 data-reveal className="l-display mb-8 text-center text-2xl sm:text-3xl">و خیلی چیزهای دیگر</h3>
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {MORE.map((m, i) => (
                <li key={m.title} data-reveal style={{ '--i': i % 3 } as CSSProperties} className="l-card flex gap-4 border border-border bg-surface p-5">
                  <Icon icon={m.icon} />
                  <div><p className="font-bold">{m.title}</p><p className="mt-1 text-sm leading-6 text-text-muted">{m.text}</p></div>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* How to start */}
        <section aria-labelledby="start" className="bg-brand text-on-brand">
          <div className="mx-auto max-w-6xl px-5 py-20 sm:px-8">
            <h2 id="start" data-reveal className="l-display text-center text-3xl sm:text-4xl">امروز شروع کنید، امروز سفارش بگیرید</h2>
            <ol className="mt-12 grid gap-5 md:grid-cols-3">
              {STEPS.map((s, i) => (
                <li key={s.title} data-reveal style={{ '--i': i } as CSSProperties} className="l-card relative flex flex-col gap-3 bg-on-brand/10 p-6">
                  <span className="absolute end-5 top-4 text-5xl font-black opacity-20">{['۱', '۲', '۳'][i]}</span>
                  <s.icon className="size-7" aria-hidden="true" />
                  <p className="text-lg font-bold">{s.title}</p>
                  <p className="text-sm leading-7 opacity-85">{s.text}</p>
                </li>
              ))}
            </ol>
            <div data-reveal className="mt-10 flex justify-center">
              <Link href="/signup" className="l-pill inline-flex h-14 items-center gap-2 bg-on-brand px-8 text-lg font-bold text-brand shadow-[var(--shadow-md)] hover:opacity-90">شروع رایگان ۱۴ روزه<ArrowLeft className="size-4" aria-hidden="true" /></Link>
            </div>
          </div>
        </section>

        {/* Pricing */}
        <section id="pricing" className="l-texture scroll-mt-20 overflow-hidden">
          <div className="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-28">
            <div className="mb-10 text-center">
              <span data-reveal className="l-eyebrow text-brand">قیمت‌ها</span>
              <h2 data-reveal className="l-display mt-3 text-3xl sm:text-5xl">ساده و روشن، بدون هزینه‌ی پنهان</h2>
              <p data-reveal className="mx-auto mt-4 max-w-xl text-text-muted">هر وقت خواستید پلن را عوض کنید؛ اطلاعات‌تان همیشه سر جایش است.</p>
            </div>
            {pricing && pricing.plans.length ? (
              <Pricing plans={pricing.plans} features={pricing.features} vat={pricing.vat_rate} trialDays={pricing.trial_days} />
            ) : (
              <div className="flex justify-center"><Cta /></div>
            )}
          </div>
        </section>

        {/* FAQ */}
        <section id="faq" className="scroll-mt-20 bg-surface-muted/60">
          <div className="mx-auto max-w-3xl px-5 py-20 sm:px-8">
            <h2 data-reveal className="l-display mb-8 text-center text-3xl sm:text-4xl">سؤال‌های رایج</h2>
            <div className="flex flex-col gap-3">
              {FAQ.map(([q, a], i) => (
                <details key={q} data-reveal style={{ '--i': i % 3 } as CSSProperties} className="group l-card-sm border border-border bg-surface px-5 py-4 open:shadow-[var(--shadow-sm)]">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                    {q}<span aria-hidden="true" className="text-xl text-text-subtle transition-transform group-open:rotate-45">+</span>
                  </summary>
                  <p className="mt-3 leading-8 text-text-muted">{a}</p>
                </details>
              ))}
            </div>
          </div>
        </section>

        {/* Last call */}
        <section className="l-texture overflow-hidden">
          <div className="mx-auto flex max-w-4xl flex-col items-center gap-6 px-5 py-24 text-center sm:px-8">
            <h2 data-reveal className="l-display text-3xl sm:text-5xl">کافه یا رستوران دارید؟ از همین امروز.</h2>
            <p data-reveal className="max-w-xl text-lg leading-8 text-text-muted">۱۴ روز همه‌ی امکانات را امتحان کنید. اگر به کارتان نیامد، کافی است ادامه ندهید.</p>
            <div data-reveal><Cta big /></div>
          </div>
        </section>
      </main>

      <footer className="border-t border-border bg-surface">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-8 text-sm text-text-muted sm:flex-row sm:px-8">
          <span className="flex items-center gap-2"><span className="flex size-8 items-center justify-center rounded-lg bg-brand text-on-brand"><Coffee className="size-4" aria-hidden="true" /></span><span className="font-bold text-text">کافه‌یار</span></span>
          <nav aria-label="پیوندها" className="flex flex-wrap justify-center gap-x-5 gap-y-2">
            <Link href="/explore" className="hover:text-text">خوراک‌گردی</Link>
            <Link href="/signup" className="hover:text-text">شروع رایگان</Link>
            <Link href="/login" className="hover:text-text">ورود به پنل</Link>
          </nav>
        </div>
      </footer>
    </LandingFrame>
  );
}
