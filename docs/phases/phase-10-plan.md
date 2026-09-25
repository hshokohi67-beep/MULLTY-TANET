# Phase 10: Operations (Expenses, Staff, Shifts, Attendance) (Plan)

**Status:** in progress
**Depends on:** Phase 1, and Phase 9 for the profit picture. Phase 9 was approved on 2026-09-25.
**Roadmap scope:** expenses + categories, employees, roles, shifts, assignments, attendance.

## 1. Model (new module `Operations`)

| Table | Contents |
|---|---|
| `expense_categories` | name, colour index, active. Seeded with sensible defaults: اجاره (rent), قبوض (utilities), تعمیرات (repairs), تبلیغات (advertising), حمل و نقل (transport), مالیات و عوارض (tax and levies), متفرقه (other) |
| `expenses` | branch, category, amount (rial), local `spent_on` date, method (cash/card/transfer), payee, note, recorded_by |
| `employees` | name, phone, position (e.g. «باریستا», barista), branch, pay type (`hourly` / `monthly`), rate (rial per hour or per month), optional linked staff `user_id` (for self clock-in), hired_on, active |
| `shifts` | employee, branch, `starts_at` / `ends_at` (UTC), note. Overnight shifts are allowed |
| `attendance_records` | employee, branch, `clock_in_at`, `clock_out_at` (nullable while on shift), `shift_id` (matched), source (`self` / `manager`), note, edited_by |

## 2. Rules
- **Expenses:** amounts are integer rial; the date is the tenant-local business day.
- **Shifts:** can't overlap for the same employee; a shift must end after it starts and last at most 16 h.
- **Attendance:**
  - clocking in is refused while already clocked in;
  - clocking out closes the open record;
  - a record is matched to the shift that overlaps it;
  - **late** = clock-in more than 10 minutes after the shift start;
  - edits by a manager keep `edited_by`.
- **Labour cost:**
  - hourly staff: worked minutes × hourly rate;
  - monthly staff: `rate / standard monthly hours` per hour (tenant setting `staff.monthly_hours`, default 192) × worked minutes.

  So the cost of a day or a range is comparable.
- **Self clock-in:** only for an employee linked to the signed-in staff user, within their tenant.

## 3. API

| Group | Endpoints | Permission |
|---|---|---|
| Expenses | categories CRUD, expenses CRUD (filters: month/range, branch, category), a summary by category | `expenses.manage` |
| Staff | employees CRUD | `staff.manage` |
| Shifts | `GET /shifts?from&to&branch_id`, create/update/delete; copy last week | `staff.manage` |
| Attendance | list/add/edit/delete | `staff.manage` |
| Self clock-in | `GET /attendance/me`; `POST /attendance/clock-in` / `clock-out` | `attendance.self` (every default role) |
| Payroll | `GET /staff/payroll?from&to`: per employee worked hours, shifts, late count, labour cost | `staff.manage` |

## 4. Insights
- Widget **`profit`** («سود و زیان», profit and loss) for the range:
  - sales, cost of goods (Phase 9), labour (attendance), expenses;
  - operating profit and margin;
  - **prime cost %** = (COGS + labour) / sales.
- Widget **`labour`**: who is on shift now, today's labour cost vs today's sales.
- Widget **`expenses`**: the range by category (share bar).
- Alert: «N نفر هنوز خروج نزده‌اند» ("N people haven't clocked out") when a record is open more than 16 h.

## 5. Panel
- **«هزینه‌ها» (expenses):**
  - Jalali month switcher, total and share by category, list, add/edit drawer (toman, category, Jalali date, method, payee, note);
  - categories manager.
- **«کارکنان» (staff), with tabs:**
  - **کارکنان** (staff): list, add/edit drawer, link to a panel user;
  - **برنامه‌ی شیفت** (shift schedule): a weekly grid, Saturday first, with Jalali dates; click a cell to add a shift (`ClockSelect`), click a shift to edit or delete; copy last week;
  - **حضور و غیاب** (attendance): who is in now; records by day with late badges; manual add/edit;
  - **حقوق و دستمزد** (payroll): range summary per employee with totals.
- **Top bar:** a «ورود / خروج» (clock in / out) button for staff who are linked employees, showing the time since clock-in.

## 6. Tests
- **Expenses:** CRUD, filters, summary.
- **Shifts:** overlap refused, overnight allowed, copy-week.
- **Attendance:** clock-in twice refused; clock-out; late detection; manager edit; self clock-in only for the linked employee.
- **Payroll:** hourly vs monthly rates.
- **Widgets:** profit (with COGS and expenses) and labour.
- **Permissions; isolation harness:** foreign employee/shift/expense IDs, and a self clock-in of another tenant's employee.
