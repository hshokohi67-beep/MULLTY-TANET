import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { HelpArticle } from '@/components/help/HelpArticle';
import { PLATFORM_TOPICS } from '@/lib/help';

export async function generateMetadata({ params }: PageProps<'/platform/help/[topic]'>): Promise<Metadata> {
  const { topic: key } = await params;
  const topic = PLATFORM_TOPICS.find((t) => t.key === key);

  return { title: topic ? `راهنما: ${topic.title}` : 'راهنما' };
}

export default async function PlatformHelpTopicPage({ params }: PageProps<'/platform/help/[topic]'>) {
  const { topic: key } = await params;
  const index = PLATFORM_TOPICS.findIndex((t) => t.key === key);
  if (index < 0) notFound();
  const topic = PLATFORM_TOPICS[index];
  const related = (topic.related ?? []).map((k) => PLATFORM_TOPICS.find((t) => t.key === k)).filter((t) => t !== undefined);

  return (
    <HelpArticle topic={topic} base="/platform/help" related={related}
      prev={PLATFORM_TOPICS[index - 1] ?? null} next={PLATFORM_TOPICS[index + 1] ?? null} canOpenScreen locked={false} />
  );
}
