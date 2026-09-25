import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { HelpArticle } from '@/components/help/HelpArticle';
import { STORE_TOPICS } from '@/lib/help';
import { helpReader } from '@/lib/help/reader';

export async function generateMetadata({ params }: PageProps<'/dashboard/help/[topic]'>): Promise<Metadata> {
  const { topic: key } = await params;
  const topic = STORE_TOPICS.find((t) => t.key === key);

  return { title: topic ? `راهنما: ${topic.title}` : 'راهنما' };
}

/** One help topic, if this user's role can open it (otherwise 404: each role's help stays focused). */
export default async function HelpTopicPage({ params }: PageProps<'/dashboard/help/[topic]'>) {
  const { topic: key } = await params;
  const { topics, locked, can } = await helpReader();
  const index = topics.findIndex((t) => t.key === key);
  if (index < 0) notFound();
  const topic = topics[index];
  const related = (topic.related ?? []).map((k) => topics.find((t) => t.key === k)).filter((t) => t !== undefined);

  return (
    <HelpArticle topic={topic} base="/dashboard/help" related={related}
      prev={topics[index - 1] ?? null} next={topics[index + 1] ?? null}
      canOpenScreen={topic.anyOf.length === 0 || topic.anyOf.some((p) => can(p))} locked={locked(topic)} />
  );
}
