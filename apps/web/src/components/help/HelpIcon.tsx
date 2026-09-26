import {
  Armchair, BarChart3, BookOpen, Boxes, CalendarClock, ChefHat, Clock, Compass, Crown, CreditCard, LayoutDashboard, Megaphone, MonitorPlay, Receipt,
  ReceiptText, Rocket, Search, Settings, ShieldCheck, ShoppingCart, Smartphone, Store, Tags, Truck, UserCog, UserRound, Users, UtensilsCrossed, Wallet, Aperture, PanelsTopLeft,
  type LucideIcon,
} from 'lucide-react';
import type { HelpIcon as Key } from '@/lib/help';

const ICONS: Record<Key, LucideIcon> = {
  overview: LayoutDashboard, orders: ReceiptText, kds: MonitorPlay, kitchen: ChefHat, tables: Armchair, menu: UtensilsCrossed, discounts: Tags,
  stories: Aperture, landing: PanelsTopLeft, delivery: Truck, marketplace: Compass, ads: Megaphone, inventory: Boxes, purchases: ShoppingCart, staff: CalendarClock,
  clock: Clock, expenses: Receipt, customers: Users, club: Crown, payments: Wallet, reports: BarChart3, branches: Store, team: UserCog,
  settings: Settings, billing: CreditCard, storefront: Smartphone, search: Search, role: UserRound, platform: ShieldCheck, start: Rocket,
};

export function HelpIcon({ name, className }: { name: Key; className?: string }) {
  const Icon = ICONS[name] ?? BookOpen;

  return <Icon className={className} aria-hidden="true" />;
}
