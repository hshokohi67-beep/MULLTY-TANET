'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { CircleHelp } from 'lucide-react';
import { topicForPath } from '@/lib/help/screens';

/** «راهنمای این صفحه»: opens the help topic of the current screen (or the help centre). */
export function HelpButton({ map, base = '/dashboard/help' }: { map: Record<string, string>; base?: string }) {
  const pathname = usePathname();
  const key = pathname.startsWith(base) ? null : topicForPath(map, pathname);

  return (
    <Link href={key ? `${base}/${key}` : base} aria-label="راهنمای این صفحه" title="راهنمای این صفحه"
      className="hidden h-9 items-center gap-1.5 rounded-lg border border-border bg-surface px-2.5 text-sm text-text-muted transition-colors hover:text-text sm:inline-flex">
      <CircleHelp className="size-4" aria-hidden="true" /><span className="hidden lg:inline">راهنما</span>
    </Link>
  );
}
