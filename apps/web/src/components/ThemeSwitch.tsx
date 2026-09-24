'use client';

import { useEffect, useState } from 'react';
import { Monitor, Moon, Sun } from 'lucide-react';

type Theme = 'system' | 'light' | 'dark';
const ORDER: Theme[] = ['system', 'light', 'dark'];
const LABEL: Record<Theme, string> = { system: 'پوسته: هماهنگ با دستگاه', light: 'پوسته: روشن', dark: 'پوسته: تیره' };

/** Cycles system → light → dark. The choice is applied before paint by the script in the root layout. */
export function ThemeSwitch() {
  const [theme, setTheme] = useState<Theme>('system');

  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        const saved = localStorage.getItem('theme');
        if (saved === 'light' || saved === 'dark') setTheme(saved);
      } catch {
        // storage unavailable
      }
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  const next = () => {
    const value = ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length];
    setTheme(value);
    try {
      if (value === 'system') localStorage.removeItem('theme');
      else localStorage.setItem('theme', value);
    } catch {
      // storage unavailable: applies to this page only
    }
    if (value === 'system') delete document.documentElement.dataset.theme;
    else document.documentElement.dataset.theme = value;
  };

  const Icon = theme === 'light' ? Sun : theme === 'dark' ? Moon : Monitor;

  return (
    <button type="button" onClick={next} aria-label={LABEL[theme]} title={LABEL[theme]} className="flex size-9 items-center justify-center rounded-lg text-text-muted transition-colors hover:bg-surface-muted hover:text-text">
      <Icon className="size-[18px]" aria-hidden="true" />
    </button>
  );
}
