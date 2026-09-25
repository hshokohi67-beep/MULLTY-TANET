import type { Metadata } from 'next';
import { HelpIndex } from '@/components/help/HelpIndex';
import { helpReader, toIndexTopic } from '@/lib/help/reader';

export const metadata: Metadata = { title: 'راهنما' };

/** The help centre: only the topics this user's role can open, their role guide first. */
export default async function HelpPage() {
  const { roles, topics, locked, reader } = await helpReader();
  const isOwner = reader.roleKeys.includes('owner');
  const intro = isOwner
    ? 'شما همه‌ی بخش‌ها را می‌بینید، به‌همراه راهنمای شروع هر نقش تا همکاران را آموزش دهید. هر همکار در راهنمای خودش فقط بخش‌های مربوط به نقش خودش را می‌بیند.'
    : 'این راهنما فقط بخش‌هایی را نشان می‌دهد که با نقش شما در دسترس است. در هر صفحه‌ی پنل هم دکمه‌ی «راهنمای این صفحه» مستقیم به توضیح همان صفحه می‌آورد.';

  return <HelpIndex topics={topics.map((t) => toIndexTopic(t, locked(t)))} base="/dashboard/help" roleNames={roles.map((r) => r.name)} isOwner={isOwner} intro={intro} />;
}
