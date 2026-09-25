import type { Metadata } from 'next';
import { HelpIndex } from '@/components/help/HelpIndex';
import { PLATFORM_TOPICS, searchText } from '@/lib/help';

export const metadata: Metadata = { title: 'راهنمای مدیریت پلتفرم' };

/** Help for platform admins (the layout already restricts /platform to them). */
export default function PlatformHelpPage() {
  const topics = PLATFORM_TOPICS.map((t) => ({ key: t.key, title: t.title, summary: t.summary, icon: t.icon, group: t.group, locked: false, text: searchText(t), isRole: t.key === 'platform-start' }));

  return <HelpIndex topics={topics} base="/platform/help" roleNames={['مدیر پلتفرم']} isOwner={false} intro="مدیریت همه‌ی کافه‌ها: مشترکان و پلن‌ها، نظارت بر خوراک‌گردی و تصویر شهرها، و بررسی تبلیغات." />;
}
