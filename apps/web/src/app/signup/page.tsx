import type { Metadata } from 'next';
import Link from 'next/link';
import { ChefHat, Crown, LineChart, QrCode, Sparkles } from 'lucide-react';
import { getStaffToken } from '@/lib/session';
import { SignupForm } from './SignupForm';

export const metadata: Metadata = { title: 'شروع رایگان', description: 'کافه یا رستوران خود را در چند دقیقه روی کافه‌یار راه بیندازید؛ ۱۴ روز رایگان.' };

const PERKS = [
  { icon: QrCode, text: 'منوی آنلاین و سفارش با QR از همان روز اول' },
  { icon: ChefHat, text: 'نمایشگر آشپزخانه و صندوق در یک پنل' },
  { icon: Crown, text: 'باشگاه مشتریان، کیف پول و پیامک' },
  { icon: LineChart, text: 'گزارش فروش، سود و موجودی انبار' },
];

/** Self-service signup: the café, its address and its owner; then the code; then the panel. */
export default async function SignupPage() {
  // A staff cookie may be stale, so don't bounce: just offer the way back to the panel.
  const signedIn = Boolean(await getStaffToken());
  const base = (process.env.NEXT_PUBLIC_STORE_BASE_DOMAIN ?? 'cafeyar.ir').replace(/:\d+$/, '');

  return (
    <main id="main" className="grid min-h-dvh lg:grid-cols-[1fr_1.1fr]">
      <section className="flex items-center justify-center px-4 py-12">
        <div className="page-in w-full max-w-md">
          <Link href="/business" className="text-sm text-text-muted hover:text-text">→ کافه‌یار برای کسب‌وکارها</Link>
          <h1 className="mt-4 text-2xl font-bold">کافه‌تان را راه بیندازید</h1>
          <p className="mt-1.5 text-sm text-text-muted">چند دقیقه، ۱۴ روز رایگان. بعد از ساخت، راهنمای قدم‌به‌قدم کنارتان است.</p>
          <div className="mt-6"><SignupForm base={base} /></div>
          <p className="mt-6 text-center text-sm text-text-muted">
            {signedIn ? <>وارد شده‌اید؟ <Link href="/dashboard" className="font-semibold text-brand hover:underline">رفتن به پنل</Link></> : <>حساب دارید؟ <Link href="/login" className="font-semibold text-brand hover:underline">ورود</Link></>}
          </p>
        </div>
      </section>

      <aside className="relative hidden overflow-hidden bg-brand-strong text-on-brand lg:flex lg:items-center lg:justify-center" aria-hidden="true">
        <div className="absolute -start-24 -top-24 size-96 rounded-full bg-on-brand/10 blur-3xl" />
        <div className="absolute -bottom-32 -end-16 size-[28rem] rounded-full bg-accent/25 blur-3xl" />
        <div className="relative max-w-md px-10">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-on-brand/15 px-3 py-1 text-xs font-semibold"><Sparkles className="size-3.5" />۱۴ روز رایگان، بدون کارت بانکی</span>
          <p className="mt-4 text-3xl font-bold leading-snug">همه‌ی کافه در یک پنل فارسی</p>
          <ul className="mt-8 flex flex-col gap-3">
            {PERKS.map((p) => (
              <li key={p.text} className="flex items-center gap-3 rounded-2xl bg-on-brand/10 px-4 py-3">
                <p.icon className="size-5 shrink-0" />{p.text}
              </li>
            ))}
          </ul>
        </div>
      </aside>
    </main>
  );
}
