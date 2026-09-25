/** Operations API shapes (expenses, staff, shifts, attendance). Money is integer rial; instants are UTC ISO-8601. */

export interface ExpenseCategory { id: string; name: string; color: number; is_active: boolean; in_use: boolean }

export type ExpenseMethod = 'cash' | 'card' | 'transfer';

export const EXPENSE_METHODS: Record<ExpenseMethod, string> = { cash: 'نقد', card: 'کارت', transfer: 'حواله‌ی بانکی' };

export interface Expense {
  id: string;
  branch_id: string;
  category: { id: string; name: string; color: number };
  amount: number;
  spent_on: string;
  method: ExpenseMethod;
  method_label: string;
  payee: string | null;
  note: string | null;
}

export interface ExpenseSummary { total: number; categories: { id: string; name: string; color: number; amount: number }[] }

export interface Employee {
  id: string;
  name: string;
  phone: string | null;
  position: string | null;
  branch_id: string;
  user_id: string | null;
  pay_type: 'hourly' | 'monthly';
  rate: number;
  hourly_rate: number;
  hired_on: string | null;
  is_active: boolean;
}

export interface Shift { id: string; employee_id: string; branch_id: string; starts_at: string; ends_at: string; minutes: number; note: string | null }

export interface AttendanceRecord {
  id: string;
  employee: { id: string; name: string; position: string | null };
  branch_id: string;
  shift: { id: string; starts_at: string; ends_at: string } | null;
  clock_in_at: string;
  clock_out_at: string | null;
  minutes: number;
  late_minutes: number;
  source: 'self' | 'manager';
  note: string | null;
  edited: boolean;
}

export interface PayrollRow { employee_id: string; name: string; position: string | null; pay_type: 'hourly' | 'monthly'; minutes: number; shifts: number; late: number; open: boolean; cost: number }
export interface Payroll { from: string; to: string; rows: PayrollRow[]; total_minutes: number; total_cost: number }

export interface TimeClock {
  employee: { id: string; name: string };
  open: AttendanceRecord | null;
  next_shift: { starts_at: string; ends_at: string } | null;
}

/** "۷ ساعت و ۳۰ دقیقه" style duration. */
export function formatMinutes(total: number): string {
  const fa = (n: number) => new Intl.NumberFormat('fa-IR').format(n);
  const h = Math.floor(total / 60);
  const m = total % 60;
  if (h === 0) return `${fa(m)} دقیقه`;

  return m === 0 ? `${fa(h)} ساعت` : `${fa(h)} ساعت و ${fa(m)} دقیقه`;
}

/** Short "۷:۳۰" duration for dense grids. */
export function formatHours(total: number): string {
  const fa = (n: number) => new Intl.NumberFormat('fa-IR', { minimumIntegerDigits: 2, useGrouping: false }).format(n);

  return `${new Intl.NumberFormat('fa-IR').format(Math.floor(total / 60))}:${fa(total % 60)}`;
}

/** The UTC offset of a time zone at a given instant, as "+03:30". */
function offsetOf(timeZone: string, at: Date): string {
  const name = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'longOffset' }).formatToParts(at).find((p) => p.type === 'timeZoneName')?.value ?? 'GMT';
  const m = /GMT([+-]\d{2}):?(\d{2})?/.exec(name);

  return m ? `${m[1]}:${m[2] ?? '00'}` : '+00:00';
}

/** A café-local wall-clock time ("2026-09-26", "08:00") as an ISO instant with the right offset. */
export function localToIso(date: string, hhmm: string, timeZone: string): string {
  const guess = new Date(`${date}T${hhmm}:00Z`);

  return `${date}T${hhmm}:00${offsetOf(timeZone, guess)}`;
}

/** The café-local date ("YYYY-MM-DD") and time ("HH:MM") of an instant. */
export function isoToLocal(iso: string, timeZone: string): { date: string; time: string } {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
    .formatToParts(new Date(iso)).map((p) => [p.type, p.value]));

  return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}
