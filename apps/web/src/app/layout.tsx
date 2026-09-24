import type { Metadata, Viewport } from 'next';
import localFont from 'next/font/local';
import './globals.css';

const vazirmatn = localFont({
  src: './fonts/Vazirmatn-Variable.woff2',
  variable: '--font-vazirmatn',
  weight: '100 900',
  display: 'swap',
  preload: true,
});

export const metadata: Metadata = {
  title: { default: 'کافه‌یار', template: '%s | کافه‌یار' },
  description: 'مدیریت یکپارچه‌ی کافه و رستوران: منو، سفارش، باشگاه مشتریان و گزارش‌ها',
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  themeColor: '#0f766e',
};

export default function RootLayout({ children }: LayoutProps<'/'>) {
  return (
    <html lang="fa" dir="rtl" className={`${vazirmatn.variable} h-full antialiased`} suppressHydrationWarning>
      <head>
        {/* Apply the saved light/dark choice before the first paint (no flash). */}
        <script dangerouslySetInnerHTML={{ __html: "try{var t=localStorage.getItem('theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}" }} />
      </head>
      <body className="min-h-full bg-bg text-text">
        <a href="#main" className="skip-link">پرش به محتوای اصلی</a>
        {children}
      </body>
    </html>
  );
}
