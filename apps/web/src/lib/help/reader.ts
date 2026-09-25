import 'server-only';
import { requireMembership } from '@/lib/auth';
import { getBillingStatus } from '@/lib/billing';
import { searchText, visibleTopics, type HelpTopic } from '@/lib/help';

/** The signed-in staff member as a help reader: permissions, role keys and names, plan features. */
export async function helpReader() {
  const { membership, can } = await requireMembership();
  const billing = await getBillingStatus();
  const roles = membership.roles ?? [];
  const reader = { can, roleKeys: roles.map((r) => r.key) };
  const topics = visibleTopics(reader);
  const locked = (t: HelpTopic) => Boolean(t.feature && billing?.features[t.feature] === false);

  return { reader, roles, topics, locked, can };
}

export function toIndexTopic(t: HelpTopic, locked: boolean) {
  return { key: t.key, title: t.title, summary: t.summary, icon: t.icon, group: t.group, locked, text: searchText(t), isRole: Boolean(t.roles) };
}
