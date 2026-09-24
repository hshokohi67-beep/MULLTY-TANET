import type { Metadata, Viewport } from 'next';

export const metadata: Metadata = { title: 'نمایشگر آشپزخانه' };

export const viewport: Viewport = { themeColor: '#0e1116' };

/** Kitchen screens are dark by default: less glare next to bright kitchen lights, easy on the eyes all shift. */
export default function KdsLayout({ children }: LayoutProps<'/kds'>) {
  return (
    <div data-theme="dark" className="min-h-dvh bg-bg text-text">
      {children}
    </div>
  );
}
