# Phase 10: Operations — Expenses, Staff, Shifts, Attendance, Payroll (Report)

Plan: `phase-10-plan.md`. Status: done, awaiting approval.

## 1. What was built

### Module `Operations` (depends on Core / Identity; Insights reads from it)

- **Tables** (`2026_10_02_100000_create_operations_tables`), all tenant-owned with ULIDs and composite FKs:
  `expense_categories`, `expenses` (`spent_on` is a local date), `employees` (optional `user_id` → a panel user, unique per tenant; `pay_type` hourly|monthly; `rate` in rial), `shifts`, `attendance_records`.
- **Expenses**: seven default Persian categories on first visit; CRUD; a category with expenses cannot be deleted, only hidden (`category_in_use`); `GET /expenses/summary` (total + per category). Amounts are not allowed in the future (one day of slack).
- **Staff**: employees are separate from panel users (a dishwasher may never log in). Linking to a user requires membership of this café (`user_not_member`).
- **Shifts** (`ManageShifts`): no overlaps per person (row-locked check), maximum 16 h, overnight allowed; `copy-week` copies a week to another and skips duplicates or overlaps (reports `copied`/`skipped`).
- **Attendance** (`ManageAttendance`):
  - self clock-in/out via `time-clock/{me,in,out}` (`attendance.self`), with errors `already_clocked_in`, `not_clocked_in` and `not_an_employee` (403);
  - each record is matched to the shift that covers it (starting up to 2 h later);
  - late = more than 10 minutes after the shift start;
  - managers can add or correct records; those are marked `source=manager` / `edited`.
- **Payroll** (`Support\Payroll`): minutes × hourly rate. A monthly salary is spread over the standard hours (setting `staff.monthly_hours`, default 192). Cost is rounded to whole toman. Open records count up to now.
- **Validation**: write payloads use FormRequests (`ExpenseRequest`, `EmployeeRequest`, `ShiftRequest`, `AttendanceRequest`); filters stay inline.
- **Permissions**:
  - `expenses.manage`, `staff.manage`, `attendance.self`;
  - the manager gets all three; cashier, kitchen and waiter get `attendance.self`.
- **Insights**:
  - widget `profit`: sales − cost of goods − labour − expenses, with margin, prime cost and recipe coverage;
  - widget `labour`: who is in, cost so far, labour as a share of sales;
  - widget `expenses`: by category;
  - alert `open_attendance`: someone has been clocked in for more than 16 h;
  - defaults: owner gets `profit`, manager gets `labour`.
- **Demo data**: `OperationsDemoSeeder` adds 4 employees (the demo cashier is linked), this week's plan, attendance so far, and two months of expenses.

### Panel

- **«کارکنان و شیفت»** (Staff and shifts, `/dashboard/staff`):
  - four stat tiles: active staff, on shift now, hours worked today, late arrivals today;
  - tab *Schedule*: a Saturday-first week grid (row per person, column per day, today highlighted, per-person and per-day totals). Clicking an empty cell adds a shift and clicking a chip edits it. Uses `ClockSelect` at a 15-minute step and labels overnight shifts; one click copies the week to the next week;
  - tab *Attendance*: cards for who is in now (live duration, a warning after 16 h, one-tap clock-out), the day's records with late and "entered by manager" badges, manual entry and correction;
  - tab *Payroll*: Jalali month switcher; table with rate, shifts, hours, late count and pay; totals;
  - tab *Staff*: cards (pay, linked account, phone) and a drawer form (hourly/monthly toggle, link to a team member, active).
- **«هزینه‌ها»** (Expenses, `/dashboard/expenses`):
  - Jalali month switcher; stat tiles (month total with a delta against the *same days* of the previous month, count, daily average, largest category);
  - the list is grouped by day and can be filtered by category; add/edit uses a drawer (toman, Jalali date, method cards, branch, payee);
  - category share bar; an inline category manager (rename, hide, delete when unused).
- **Top-bar time clock** (`TimeClockButton`): shown only to users linked to an employee. «ورود» ("clock in") → «سر کار ۲:۱۵» ("on shift 2:15"). Clock-out needs a second tap («تأیید خروج», "confirm clock-out") instead of a browser dialog.
- **Navigation and palette**: new nav group «کارکنان و هزینه‌ها» ("staff and expenses"); palette keywords and actions: add an expense, this week's shifts, this month's payroll.
- **Widgets** `ProfitWidget`, `LabourWidget`, `ExpensesWidget`. Extreme ratios are shown as «بیش از ۱۰۰٪» ("over 100%") instead of four-digit percentages.

## 2. Verification

- API: 430 tests, 5114 assertions green (`tests/Feature/Operations/OperationsTest.php`, 4 scenarios).
- Tenant isolation harness:
  - every Operations list and write endpoint, plus the time clock;
  - every `{expenseCategory}`, `{expense}`, `{employee}`, `{shift}`, `{attendanceRecord}` route checked against tenant B's records;
  - foreign `employee_id`/`category_id` in request bodies are rejected;
  - B's user cannot be linked as an employee of A.
- Larastan level 6: 0 errors. Pint clean.
- Web: typecheck, lint and build green. `@cafe/ui` 6 and `@cafe/locale` 8 tests green.
- Visual check (headless Chrome):
  - staff tabs, expenses, the shift dialog (dark), the expense drawer (390 px), the dashboard with the new widgets (light and dark);
  - no horizontal overflow at 390 px on any of the new pages.
- Fixed during the check:
  - fractional toman in payroll and the daily average;
  - a misleading "−99%" month delta early in the month;
  - the tab bar widening the page on phones;
  - phone digits;
  - missing top padding in the food-cost widget.

## 3. Notes

- Times are sent to the API as ISO instants with the café's offset (`localToIso`), so shifts stay correct regardless of the browser's time zone.
- Payroll is a basis for payslips, not a replacement for them: there are no taxes, insurance, overtime multipliers or leave.
- An employee without a panel account is clocked in and out by a manager on the Attendance tab.
- Possible follow-ups:
  - kiosk or PIN clock-in on a shared tablet;
  - overtime rules;
  - payroll CSV export;
  - recurring expenses (e.g. monthly rent).
