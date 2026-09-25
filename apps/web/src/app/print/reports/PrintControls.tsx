'use client';

import { useEffect } from 'react';
import { Printer } from 'lucide-react';
import { Button } from '@cafe/ui';

/** Forces the light theme for paper, and offers the browser's print / "Save as PDF". */
export function PrintControls() {
  useEffect(() => {
    const root = document.documentElement;
    const previous = root.dataset.theme;
    root.dataset.theme = 'light';

    return () => {
      if (previous) root.dataset.theme = previous;
      else delete root.dataset.theme;
    };
  }, []);

  return (
    <div className="no-print sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-border bg-surface/90 px-6 py-3 backdrop-blur">
      <p className="text-sm text-text-muted">برای PDF، در پنجره‌ی چاپ مقصد را «Save as PDF» انتخاب کنید.</p>
      <Button icon={<Printer />} onClick={() => window.print()}>چاپ / ذخیره‌ی PDF</Button>
    </div>
  );
}
