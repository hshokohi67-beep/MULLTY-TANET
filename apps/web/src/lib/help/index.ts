import { BUSINESS_TOPICS } from './topics-business';
import { PLATFORM_TOPICS } from './topics-platform';
import { SALES_TOPICS } from './topics-sales';
import { GENERAL_TOPICS, ROLE_TOPICS } from './topics-start';
import type { HelpTopic } from './types';

export { topicForPath } from './screens';
export { GROUP_TITLES, type HelpGroup, type HelpIcon, type HelpTopic } from './types';

/** Café-side topics, in reading order (role guides first). */
export const STORE_TOPICS: HelpTopic[] = [...ROLE_TOPICS, ...GENERAL_TOPICS, ...SALES_TOPICS, ...BUSINESS_TOPICS];
export { PLATFORM_TOPICS };

export interface Reader {
  can: (permission: string) => boolean;
  roleKeys: string[];
}

/** Whether this reader's role opens the topic. Owners see every role guide (to train the team). */
export function canRead(topic: HelpTopic, reader: Reader): boolean {
  if (topic.platform) return false;
  if (topic.roles) return reader.roleKeys.includes('owner') || topic.roles.some((r) => reader.roleKeys.includes(r));

  return topic.anyOf.length === 0 || topic.anyOf.some((p) => reader.can(p));
}

export function visibleTopics(reader: Reader): HelpTopic[] {
  return STORE_TOPICS.filter((t) => canRead(t, reader));
}

/** Screen path → topic key, for the «راهنمای این صفحه» button (longest prefix wins at lookup). */
export function screenMap(topics: HelpTopic[]): Record<string, string> {
  const map: Record<string, string> = {};
  for (const t of topics) if (t.screen && !(t.screen in map)) map[t.screen] = t.key;

  return map;
}

/** Everything searchable in a topic, as one lower-cased string. */
export function searchText(t: HelpTopic): string {
  const parts = [t.title, t.summary, ...t.sections.flatMap((s) => [s.title, ...(s.body ?? []), ...(s.steps ?? []), ...(s.points ?? []), s.example?.text ?? '', s.tip ?? '', s.warning ?? '']), ...(t.faq ?? []).flatMap((f) => [f.q, f.a])];

  return parts.join(' ').toLowerCase();
}
