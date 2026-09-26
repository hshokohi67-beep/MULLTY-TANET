import type { Metadata } from 'next';
import { formatJalaliDateTime, formatMoney, formatNumber } from '@cafe/locale';
import { Ltr } from '@cafe/ui';
import { api, ApiError } from '@/lib/api';
import type { PaymentResult } from '@/lib/types';

// The URL carries the gateway authority: keep it out of search engines and Referer headers.
export const metadata: Metadata = { title: 'نتیجه‌ی پرداخت', robots: { index: false, follow: false }, referrer: 'no-referrer' };

/**
 * Where the gateway sends the customer back (GET ?Authority=…&Status=…). The Status is ignored:
 * the API verifies the payment server-to-server, and repeating this (a refresh) is harmless.
 */
export default async function PaymentResultPage({ params, searchParams }: PageProps<'/s/[tenant]/pay/[payment]'>) {
  const { tenant, payment } = await params;
  const query = await searchParams;
  const authority = typeof query.Authority === 'string' ? query.Authority : null;

  let result: PaymentResult | null = null;
  let errorMessage: string | null = null;

  if (authority) {
    try {
      result = (await api<{ data: PaymentResult }>(`/public/payments/${encodeURIComponent(payment)}/verify`, {
        method: 'POST',
        auth: false,
        tenant,
        body: { authority },
      })).data;
    } catch (error) {
      errorMessage = error instanceof ApiError ? error.message : 'ارتباط با سرور برقرار نشد. چند لحظه بعد صفحه را دوباره باز کنید.';
    }
  } else {
    errorMessage = 'اطلاعات بازگشت از درگاه ناقص است.';
  }

  return (
    <div className="mx-auto flex min-h-[60dvh] max-w-md flex-col items-center justify-center gap-4 py-8 text-center">
      {result?.status === 'paid' ? (
        <>
          <div aria-hidden="true" className="flex size-16 items-center justify-center rounded-full bg-success-soft text-3xl text-success">✓</div>
          <h1 className="text-2xl font-bold">پرداخت موفق بود</h1>
          <p className="text-text-muted">
            سفارش #{formatNumber(result.order.daily_number)} در {result.order.branch} ثبت شد و به‌زودی آماده می‌شود.
          </p>
          <dl className="grid w-full grid-cols-2 gap-y-2 rounded-lg border border-border p-4 text-sm">
            <dt className="text-start text-text-muted">مبلغ</dt><dd className="text-end font-semibold">{formatMoney(result.amount)}</dd>
            {result.ref_id ? <><dt className="text-start text-text-muted">کد پیگیری</dt><dd className="text-end"><Ltr>{result.ref_id}</Ltr></dd></> : null}
            {result.card_pan ? <><dt className="text-start text-text-muted">کارت</dt><dd className="text-end"><Ltr>{result.card_pan}</Ltr></dd></> : null}
            {result.paid_at ? <><dt className="text-start text-text-muted">زمان</dt><dd className="text-end">{formatJalaliDateTime(result.paid_at)}</dd></> : null}
          </dl>
          {result.order.status !== 'cancelled' ? (
            <a href={`/s/${tenant}/track/${result.order.id}#t=${encodeURIComponent(result.order.tracking_token)}`} className="inline-flex h-11 items-center rounded-md bg-brand px-6 font-medium text-on-brand hover:bg-brand-strong">
              پیگیری سفارش
            </a>
          ) : null}
          {result.order.status === 'cancelled' ? (
            <p className="text-sm text-warning">این سفارش پیش از پرداخت لغو شده بود؛ کافه مبلغ را به شما برمی‌گرداند.</p>
          ) : null}
        </>
      ) : result?.status === 'pending' ? (
        <>
          <h1 className="text-xl font-bold">نتیجه‌ی پرداخت هنوز مشخص نیست</h1>
          <p className="text-text-muted">پاسخ درگاه دیر رسید. اگر مبلغ از حساب شما کم شده، سفارش چند دقیقه‌ی دیگر خودکار تأیید می‌شود.</p>
          <a href="" className="text-brand hover:underline">بررسی دوباره</a>
        </>
      ) : (
        <>
          <div aria-hidden="true" className="flex size-16 items-center justify-center rounded-full bg-danger-soft text-3xl text-danger">!</div>
          <h1 className="text-xl font-bold">پرداخت انجام نشد</h1>
          <p className="text-text-muted">{result?.failure_message ?? errorMessage}</p>
          <p className="text-sm text-text-muted">اگر مبلغی از حساب شما کم شده، حداکثر تا ۷۲ ساعت به حسابتان برمی‌گردد.</p>
          {result ? <p className="text-sm">می‌توانید دوباره پرداخت کنید یا در صندوق کافه پرداخت کنید.</p> : null}
        </>
      )}
    </div>
  );
}
