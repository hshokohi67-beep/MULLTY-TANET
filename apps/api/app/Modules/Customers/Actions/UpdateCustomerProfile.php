<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Exceptions\CustomerException;
use App\Modules\Customers\Models\Customer;
use App\Support\Localization\JalaliDate;

/**
 * Profile changes. A customer can set their Jalali birthday once (it triggers a gift);
 * staff can always correct it.
 */
final class UpdateCustomerProfile
{
    /**
     * @param  array{name?: ?string, birth_month?: ?int, birth_day?: ?int, marketing_opt_in?: bool, staff_note?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, bool $byStaff): Customer
    {
        if (array_key_exists('birth_month', $data) || array_key_exists('birth_day', $data)) {
            $month = $data['birth_month'] ?? null;
            $day = $data['birth_day'] ?? null;
            $changes = $month !== $customer->birth_month || $day !== $customer->birth_day;

            if ($changes) {
                if (! $byStaff && $customer->birthday_locked) {
                    throw CustomerException::birthdayLocked();
                }

                // 1403 is a leap year, so Esfand 30 is accepted.
                if (($month === null) !== ($day === null) || ($month !== null && ! JalaliDate::isValid(1403, $month, (int) $day))) {
                    throw CustomerException::invalidBirthday();
                }

                $customer->birth_month = $month;
                $customer->birth_day = $day;
                $customer->birthday_locked = $month !== null && ! $byStaff ? true : $customer->birthday_locked;
            }
        }

        if (array_key_exists('name', $data)) {
            $customer->name = $data['name'];
        }

        if (array_key_exists('marketing_opt_in', $data)) {
            $customer->marketing_opt_in = (bool) $data['marketing_opt_in'];
        }

        if ($byStaff && array_key_exists('staff_note', $data)) {
            $customer->staff_note = $data['staff_note'];
        }

        $customer->save();

        return $customer;
    }
}
