/** Billing API shapes (`/billing/*`, `/platform/*`). Amounts are integer rial; instants UTC ISO-8601. */

export type SubscriptionStateKey = 'trial' | 'active' | 'grace' | 'read_only';
export type Features = Record<string, boolean | number | null>;

export interface Plan {
  id: string; key: string; name: string; tagline: string | null;
  monthly_price: number; yearly_price: number; features: Features; is_public: boolean; is_trial_plan: boolean;
}

export interface Addon {
  id: string; key: string; name: string; description: string | null;
  monthly_price: number; yearly_price: number; grants: Record<string, boolean | number>; plans: string[] | null;
}

export interface FeatureDef { key: string; label: string; type: 'switch' | 'limit' }

export interface BillingStatus {
  state: SubscriptionStateKey; state_label: string; days_left: number | null; status: 'trialing' | 'active' | 'cancelled';
  plan: { key: string; name: string }; features: Features;
}

export interface Subscription {
  plan: Plan; cycle: 'monthly' | 'yearly'; status: 'trialing' | 'active' | 'cancelled';
  state: SubscriptionStateKey; state_label: string;
  trial_ends_at: string | null; current_period_start: string | null; current_period_end: string | null;
  ends_at: string | null; grace_ends_at: string | null; days_left: number | null; cancelled_at: string | null;
  scheduled: { plan: Plan; cycle: string } | null;
  addons: { id: string; key: string; name: string; quantity: number }[];
  features: Features;
}

export interface Invoice {
  id: string; number: string; kind: 'checkout' | 'renewal'; status: 'open' | 'paid' | 'void';
  plan: { id: string; name: string }; cycle: 'monthly' | 'yearly'; mode: string;
  lines: { label: string; amount: number }[]; subtotal: number; credit: number; vat_rate: number; vat: number; total: number;
  period_start: string | null; period_end: string | null; due_at: string | null; paid_at: string | null;
  paid_via: string | null; reference: string | null; created_at: string;
}

export interface Quote {
  mode: 'pay_now' | 'renew' | 'scheduled'; plan: Plan; cycle: 'monthly' | 'yearly'; addons: { addon_id: string; quantity: number }[];
  lines: { label: string; amount: number }[]; subtotal: number; credit: number; vat_rate: number; vat: number; total: number;
  period_start: string | null; period_end: string | null; warnings: string[];
}

export interface Selection { plan_id: string; cycle: 'monthly' | 'yearly'; addons: { addon_id: string; quantity: number }[] }

export const USAGE_LABELS: Record<string, string> = { branches: 'شعبه', staff: 'عضو تیم', products: 'محصول', monthly_orders: 'سفارش این ماه' };

/** Which plan feature each gated screen needs (for locked navigation and the upgrade prompt). */
export const SCREEN_FEATURES: Record<string, string> = {
  '/dashboard/reports': 'reports',
  '/dashboard/inventory': 'inventory',
  '/dashboard/purchases': 'inventory',
  '/dashboard/staff': 'operations',
  '/dashboard/expenses': 'operations',
  '/dashboard/stories': 'stories',
  '/dashboard/club': 'loyalty',
};

export const FEATURE_PITCH: Record<string, { title: string; text: string }> = {
  reports: { title: 'گزارش‌ها', text: 'سود و زیان، پرفروش‌ها با دسته‌بندی ABC، ساعت‌های شلوغ و خروجی اکسل و PDF.' },
  inventory: { title: 'انبار و خرید', text: 'موجودی مواد اولیه، دستور پخت، بهای تمام‌شده و خرید از تأمین‌کننده.' },
  operations: { title: 'کارکنان و هزینه‌ها', text: 'برنامه‌ی شیفت، ورود و خروج، دستمزد و هزینه‌های جاری.' },
  stories: { title: 'استوری', text: 'استوری‌های تصویری روی منوی آنلاین با لینک به محصول.' },
  loyalty: { title: 'باشگاه مشتریان', text: 'امتیاز، کش‌بک، سطح‌بندی و کیف پول برای برگرداندن مشتری.' },
  online_payments: { title: 'پرداخت آنلاین', text: 'پرداخت با درگاه در منوی آنلاین و سفارش از میز.' },
  custom_domain: { title: 'دامنه‌ی اختصاصی', text: 'منوی آنلاین روی دامنه‌ی خودتان.' },
};
