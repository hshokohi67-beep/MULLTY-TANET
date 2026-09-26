/**
 * The help centre's content model. Topics are plain data so they can be searched, filtered by the
 * reader's permissions and kept next to the screens they describe (update a topic with its screen).
 */

export type HelpIcon =
  | 'overview' | 'orders' | 'kds' | 'kitchen' | 'tables' | 'menu' | 'discounts' | 'stories' | 'landing' | 'delivery' | 'marketplace' | 'ads'
  | 'inventory' | 'purchases' | 'staff' | 'clock' | 'expenses' | 'customers' | 'club' | 'payments' | 'reports' | 'branches' | 'team'
  | 'settings' | 'billing' | 'storefront' | 'search' | 'role' | 'platform' | 'start';

export type HelpGroup = 'start' | 'daily' | 'menu' | 'stock' | 'people' | 'money' | 'setup' | 'platform';

export const GROUP_TITLES: Record<HelpGroup, string> = {
  start: 'شروع کار',
  daily: 'عملیات روزانه',
  menu: 'منو و فروش',
  stock: 'انبار و خرید',
  people: 'کارکنان و مشتریان',
  money: 'مالی و گزارش',
  setup: 'تنظیمات و اشتراک',
  platform: 'مدیریت پلتفرم',
};

export interface HelpExample { title: string; text: string }

export interface HelpSection {
  title: string;
  /** Paragraphs. */
  body?: string[];
  /** Numbered steps. */
  steps?: string[];
  /** Bulleted facts. */
  points?: string[];
  example?: HelpExample;
  tip?: string;
  warning?: string;
}

export interface HelpTopic {
  key: string;
  title: string;
  icon: HelpIcon;
  group: HelpGroup;
  /** One or two sentences: what this is for. */
  summary: string;
  /** Opens for anyone holding at least one of these permissions (empty = everyone). */
  anyOf: string[];
  /** Only for these role keys (role guides); owners see every role guide. */
  roles?: string[];
  /** Platform admins only (shown under /platform/help). */
  platform?: boolean;
  /** Plan feature that unlocks the screen, if any. */
  feature?: string;
  /** The screen it explains; also used by «راهنمای این صفحه». */
  screen?: string;
  sections: HelpSection[];
  faq?: { q: string; a: string }[];
  related?: string[];
}
