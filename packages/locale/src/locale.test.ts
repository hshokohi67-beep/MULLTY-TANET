import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  formatJalaliDate,
  formatJalaliLong,
  formatMoney,
  formatNumber,
  formatPhone,
  formatPostalCode,
  formatTime,
  normalizeForSearch,
  normalizeIranianMobile,
  toLatinDigits,
  toRials,
} from './index.ts';

test('numbers use Persian digits and separator', () => {
  assert.equal(formatNumber(125000), '۱۲۵٬۰۰۰');
  assert.equal(toLatinDigits('۰۹۱۲٠٩'), '091209');
});

test('money: integer rial in, toman text out', () => {
  assert.equal(formatMoney(1_250_000), '۱۲۵٬۰۰۰ تومان');
  assert.equal(formatMoney(1_250_000, { unit: 'rial' }), '۱٬۲۵۰٬۰۰۰ ریال');
  assert.equal(formatMoney(1_250_000, { withUnit: false }), '۱۲۵٬۰۰۰');
  assert.equal(toRials(125_000), 1_250_000);
  assert.throws(() => formatMoney(10.5));
});

test('jalali dates follow the Tehran day', () => {
  assert.equal(formatJalaliDate('2026-09-24T10:00:00Z'), '۱۴۰۵/۰۷/۰۲');
  assert.equal(formatJalaliDate('2026-09-23T21:00:00Z'), '۱۴۰۵/۰۷/۰۲'); // 00:30 in Tehran
  assert.equal(formatJalaliLong('2026-09-24T10:00:00Z'), '۲ مهر ۱۴۰۵');
  assert.equal(formatTime('2026-09-24T10:05:00Z'), '۱۳:۳۵');
});

test('phone normalization mirrors the backend', () => {
  for (const input of ['09121234567', '۰۹۱۲۱۲۳۴۵۶۷', '+989121234567', '00989121234567', '989121234567', '9121234567', '0912 123-4567']) {
    assert.equal(normalizeIranianMobile(input), '+989121234567', input);
  }
  assert.equal(normalizeIranianMobile('02188776655'), null);
  assert.equal(formatPhone('+989121234567'), '۰۹۱۲ ۱۲۳ ۴۵۶۷');
  assert.equal(formatPostalCode('1996835111'), '۱۹۹۶۸-۳۵۱۱۱');
});

test('search normalization mirrors the backend', () => {
  assert.equal(normalizeForSearch('كافه'), normalizeForSearch('کافه'));
  assert.equal(normalizeForSearch('  کیک   ۲ نفره '), 'کیک 2 نفره');
  assert.equal(normalizeForSearch('می‌خواهم'), 'می خواهم');
  assert.equal(normalizeForSearch('قهوة'), 'قهوه');
});

import { addDays, gregorianToJalali, isJalaliLeapYear, jalaliDaysInMonth, jalaliToGregorian, todayIn } from './jalali.ts';

test('jalali ⇄ gregorian round trips and known dates', () => {
  assert.equal(jalaliToGregorian({ year: 1405, month: 7, day: 2 }), '2026-09-24');
  assert.equal(jalaliToGregorian({ year: 1403, month: 1, day: 1 }), '2024-03-20');
  assert.deepEqual(gregorianToJalali('2026-09-24'), { year: 1405, month: 7, day: 2 });
  assert.deepEqual(gregorianToJalali('2025-03-20'), { year: 1403, month: 12, day: 30 });
  assert.equal(isJalaliLeapYear(1403), true);
  assert.equal(isJalaliLeapYear(1404), false);
  assert.equal(jalaliDaysInMonth(1404, 12), 29);
  for (let d = 0; d < 800; d += 7) {
    const g = addDays('2024-01-01', d);
    assert.equal(jalaliToGregorian(gregorianToJalali(g)), g);
  }
  assert.equal(todayIn('Asia/Tehran', new Date('2026-09-23T21:00:00Z')), '2026-09-24'); // 00:30 Tehran
  assert.equal(addDays('2026-03-01', -1), '2026-02-28');
});

import { formatMoneyCompact } from './money.ts';

test('compact money', () => {
  assert.equal(formatMoneyCompact(25_000_000), '۲٫۵ میلیون تومان');
  assert.equal(formatMoneyCompact(6_500_000, { withUnit: false }), '۶۵۰ هزار');
  assert.equal(formatMoneyCompact(10_000_000_000), '۱ میلیارد تومان');
  assert.equal(formatMoneyCompact(5_000), '۵۰۰ تومان');
});

test('long jalali date with weekday reads naturally', () => {
  assert.equal(formatJalaliLong('2026-09-24T10:00:00Z', 'Asia/Tehran', true), 'پنجشنبه ۲ مهر ۱۴۰۵');
  assert.equal(formatJalaliLong('2026-09-24T10:00:00Z'), '۲ مهر ۱۴۰۵');
});
