import type { Metadata } from 'next';
import { ChefHat, Crown, LineChart, ReceiptText } from 'lucide-react';
import { LoginForm } from './LoginForm';

export const metadata: Metadata = { title: 'ورود به پنل مدیریت' };

const FEATURES = [
  { icon: ReceiptText, text: 'سفارش‌های میز، بیرون‌بر و آنلاین در یک تابلو' },
  { icon: ChefHat, text: 'نمایشگر آشپزخانه با ایستگاه‌های جدا' },
  { icon: Crown, text: 'باشگاه مشتریان، کیف پول و کش‌بک' },
  { icon: LineChart, text: 'پیشخوان مدیریتی با گزارش لحظه‌ای فروش' },
];

export default async function LoginPage({ searchParams }: PageProps<'/login'>) {
  const params = await searchParams;
  const next = typeof params.next === 'string' ? params.next : '/dashboard';
  const expired = params.expired === '1';

  return (
    <main id="main" className="grid min-h-dvh lg:grid-cols-[1fr_1.1fr]">
      <section className="flex items-center justify-center px-4 py-12">
        <div className="page-in w-full max-w-sm">
          <div className="mb-8">
            <span className="flex size-12 items-center justify-center rounded-2xl bg-brand text-on-brand shadow-[var(--shadow-md)]" aria-hidden="true">
              <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1" /><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" /><path d="M6 2v2M10 2v2M14 2v2" /></svg>
            </span>
            <h1 className="mt-5 text-2xl font-bold">ورود به کافه‌یار</h1>
            <p className="mt-1.5 text-sm text-text-muted">با شماره موبایل یا ایمیل خود وارد پنل مدیریت شوید.</p>
          </div>
          <LoginForm next={next} expired={expired} />
        </div>
      </section>

      <aside className="relative hidden overflow-hidden bg-brand-strong text-white lg:flex lg:items-center lg:justify-center" aria-hidden="true">
        <div className="absolute -start-24 -top-24 size-96 rounded-full bg-white/10 blur-3xl" />
        <div className="absolute -bottom-32 -end-16 size-[28rem] rounded-full bg-accent/25 blur-3xl" />
        <div className="relative max-w-md px-10">
          <p className="text-3xl font-bold leading-snug">مدیریت کافه، آرام و یکپارچه</p>
          <p className="mt-3 text-white/75">همه‌چیز از سفارش تا باشگاه مشتریان، فارسی و آماده برای ایران.</p>
          <ul className="mt-8 flex flex-col gap-3">
            {FEATURES.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm">
                <Icon className="size-5 shrink-0 text-white/90" />
                <span className="text-sm">{text}</span>
              </li>
            ))}
          </ul>
        </div>
      </aside>
    </main>
  );
}
