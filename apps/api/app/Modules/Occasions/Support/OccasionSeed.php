<?php

namespace App\Modules\Occasions\Support;

use App\Modules\Occasions\Models\Occasion;

/**
 * The calendar the platform ships with. `install()` only adds missing keys, so a platform admin's
 * edits are never overwritten by a later deploy. Texts are reviewed with the owner before release.
 *
 * Calendars: jalali (month/day of the Iranian calendar), hijri (lunar; approximate until confirmed),
 * gregorian. `rule` covers dates that are not a fixed day (Chaharshanbe Suri, Black Friday).
 */
final class OccasionSeed
{
    public static function install(): void
    {
        foreach (self::all() as $row) {
            Occasion::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            // ── Iranian national ──────────────────────────────────────────────
            self::row('nowruz', 'نوروز', 'national', 'festive', 'jalali', 1, 1, 4, 21, true, 'nowruz',
                'بزرگ‌ترین جشن سال؛ تعطیلات طولانی، مهمانی و دید و بازدید. بعضی مشتری‌ها در سفرند و مسافرها به شهر شما می‌آیند.',
                ['ساعت کاری تعطیلات را از همین حالا در «شعبه‌ها» تنظیم کنید', 'منوی بهاری: شربت‌های گل و نعناع، نوشیدنی زعفرانی', 'جعبه‌ی هدیه‌ی شیرینی و قهوه برای دید و بازدید', 'تخفیف «عیدی» برای روزهای اول فروردین'],
                'نوروزتان پیروز! با جعبه‌های عیدی و نوشیدنی‌های بهاری منتظرتان هستیم.'),
            self::row('sizdah_bedar', 'سیزده‌بدر (روز طبیعت)', 'national', 'festive', 'jalali', 1, 13, 1, 10, true, 'nowruz',
                'همه بیرون از خانه‌اند؛ سفارش بیرون‌بر و پیک بالا می‌رود.',
                ['بسته‌ی پیک‌نیک: قهوه‌ی سرد در بطری، ساندویچ و کیک', 'سفارش پیش از ظهر برای تحویل صبح زود'],
                'بسته‌ی پیک‌نیک سیزده‌بدر را از امروز سفارش دهید.'),
            self::row('chaharshanbe_suri', 'چهارشنبه‌سوری', 'national', 'festive', 'jalali', 12, null, 1, 10, false, 'nowruz',
                'شب آخرین چهارشنبه‌ی سال؛ دورهمی شبانه و آجیل و شیرینی.',
                ['آجیل و شیرینی سنتی کنار نوشیدنی گرم', 'ساعت کاری آن شب را بیشتر کنید'],
                null, 'chaharshanbe_suri'),
            self::row('yalda', 'شب یلدا', 'national', 'festive', 'jalali', 9, 30, 1, 21, false, 'yalda',
                'طولانی‌ترین شب سال و یکی از پرفروش‌ترین شب‌ها برای کافه‌ها؛ دورهمی خانوادگی و دوستانه.',
                ['نوشیدنی انار و هندوانه، دسر انار', 'باکس یلدا: آجیل، شیرینی و دو نوشیدنی', 'رزرو میز برای دورهمی‌ها', 'کد تخفیف یلدا برای مشتریان باشگاه'],
                'یلدا مبارک! باکس یلدا و نوشیدنی‌های انار ما را امشب امتحان کنید.'),
            self::row('mehregan', 'جشن مهرگان', 'national', 'festive', 'jalali', 7, 10, 1, 10, false, 'autumn',
                'جشن ایرانی مهر و دوستی در آغاز پاییز.',
                ['نوشیدنی پاییزی دونفره', 'پیشنهاد «دو فنجان، یک دوستی»'],
                null),
            self::row('tirgan', 'جشن تیرگان', 'national', 'festive', 'jalali', 4, 13, 1, 10, false, 'summer',
                'جشن آب و باران در تابستان.',
                ['نوشیدنی‌های سرد و میوه‌ای', 'منوی خنک تابستانه'],
                null),
            self::row('sepandarmazgan', 'سپندارمذگان (روز عشق ایرانی)', 'national', 'festive', 'jalali', 12, 5, 1, 10, false, null,
                'روز گرامیداشت زن و زمین در فرهنگ ایرانی؛ هدیه دادن و قرار دونفره.',
                ['پیشنهاد دونفره', 'دسر و نوشیدنی ویژه برای هدیه'],
                null),
            self::row('teacher_day', 'روز معلم', 'national', 'calm', 'jalali', 2, 12, 1, 10, false, null,
                'روز قدردانی از معلمان.',
                ['تخفیف ویژه برای معلمان با نشان دادن کارت', 'بسته‌ی هدیه‌ی کوچک برای خرید دانش‌آموزان'],
                null),
            self::row('student_day', 'روز دانشجو', 'national', 'calm', 'jalali', 9, 16, 1, 7, false, null,
                'کافه‌های نزدیک دانشگاه بیشترین بهره را می‌برند.',
                ['تخفیف دانشجویی', 'پیشنهاد «قهوه + کیک» با قیمت ویژه'],
                null),
            self::row('hafez_day', 'روز بزرگداشت حافظ', 'national', 'calm', 'jalali', 7, 20, 1, 7, false, 'autumn',
                'شب شعر و فال حافظ؛ مناسب کافه‌هایی که برنامه‌ی فرهنگی دارند.',
                ['فال حافظ کنار هر سفارش', 'شب شعرخوانی'],
                null),

            // ── Seasons ──────────────────────────────────────────────────────
            self::row('summer_start', 'آغاز تابستان', 'season', 'calm', 'jalali', 4, 1, 1, 14, false, 'summer',
                'هوا گرم می‌شود و سفارش نوشیدنی سرد بالا می‌رود.',
                ['نوشیدنی‌های سرد را بالای منو ببرید', 'اسموتی، آیس‌کافی و بستنی', 'منوی تابستانه با «مجموعه‌ی منو»'],
                null),
            self::row('autumn_start', 'آغاز پاییز و بازگشایی مدارس', 'season', 'calm', 'jalali', 7, 1, 1, 14, false, 'autumn',
                'هوا خنک می‌شود؛ وقت نوشیدنی‌های گرم و ادویه‌دار.',
                ['طعم‌های پاییزی: دارچین، کدو حلوایی، سیب و ادویه', 'منوی پاییزه با «مجموعه‌ی منو»'],
                null),
            self::row('winter_start', 'آغاز زمستان', 'season', 'calm', 'jalali', 10, 1, 1, 14, false, 'winter',
                'سرمای هوا؛ نوشیدنی‌های گرم و شکلاتی پرفروش می‌شوند.',
                ['هات‌چاکلت، چای ماسالا، سحلب', 'منوی زمستانه با «مجموعه‌ی منو»'],
                null),

            // ── Religious (joyful) ───────────────────────────────────────────
            self::row('mothers_day', 'روز زن و مادر', 'religious_joy', 'festive', 'hijri', 6, 20, 1, 21, false, null,
                'ولادت حضرت فاطمه (س)؛ از پرخریدترین روزهای هدیه.',
                ['بسته‌ی هدیه برای مادر: کیک و نوشیدنی', 'سفارش از قبل برای تحویل در روز', 'کارت تبریک کنار سفارش'],
                'روز مادر مبارک! بسته‌ی هدیه‌ی روز مادر را از قبل سفارش دهید.'),
            self::row('fathers_day', 'روز پدر', 'religious_joy', 'festive', 'hijri', 7, 13, 1, 21, true, null,
                'ولادت امام علی (ع)؛ تعطیل رسمی و روز هدیه.',
                ['بسته‌ی هدیه برای پدر: قهوه‌ی دانه و کیک', 'پیشنهاد صبحانه‌ی خانوادگی'],
                'روز پدر مبارک! بسته‌ی هدیه‌ی روز پدر آماده است.'),
            self::row('mabath', 'عید مبعث', 'religious_joy', 'festive', 'hijri', 7, 27, 1, 7, true, null,
                'تعطیل رسمی.',
                ['ساعت کاری روز تعطیل را بررسی کنید'],
                null),
            self::row('nimeh_shaban', 'نیمه‌ی شعبان', 'religious_joy', 'festive', 'hijri', 8, 15, 1, 10, true, null,
                'جشن و چراغانی؛ تعطیل رسمی.',
                ['شربت و شیرینی جشن', 'ساعت کاری روز تعطیل را بررسی کنید'],
                null),
            self::row('eid_fitr', 'عید فطر', 'religious_joy', 'festive', 'hijri', 10, 1, 2, 10, true, null,
                'پایان ماه رمضان؛ دو روز تعطیل.',
                ['صبحانه‌ی عید', 'ساعت کاری روزهای تعطیل'],
                'عید فطر مبارک!'),
            self::row('dokhtar_day', 'روز دختر', 'religious_joy', 'festive', 'hijri', 11, 1, 1, 10, false, null,
                'ولادت حضرت معصومه (س).',
                ['پیشنهاد دوستانه برای دختران', 'دسر و نوشیدنی ویژه'],
                null),
            self::row('eid_adha', 'عید قربان', 'religious_joy', 'festive', 'hijri', 12, 10, 1, 7, true, null,
                'تعطیل رسمی.',
                ['ساعت کاری روز تعطیل را بررسی کنید'],
                null),
            self::row('ghadir', 'عید غدیر', 'religious_joy', 'festive', 'hijri', 12, 18, 1, 7, true, null,
                'تعطیل رسمی و جشن.',
                ['شربت و شیرینی جشن', 'ساعت کاری روز تعطیل را بررسی کنید'],
                null),

            // ── Ramadan ──────────────────────────────────────────────────────
            self::row('ramadan', 'ماه رمضان', 'ramadan', 'calm', 'hijri', 9, 1, 30, 14, false, 'ramadan',
                'الگوی خرید عوض می‌شود: روزها خلوت، از افطار تا سحر شلوغ.',
                ['ساعت کاری را با افطار و سحر هماهنگ کنید', 'منوی افطار: نوشیدنی گرم، خرما، سوپ و شیرینی', 'بسته‌ی افطاری برای بیرون‌بر', 'تخفیف «ساعت افطار» با بازه‌ی ساعتی'],
                'بسته‌های افطاری ما برای تحویل پیش از اذان مغرب آماده است.'),

            // ── Mourning (reminders, never promotions) ───────────────────────
            self::row('tasua_ashura', 'تاسوعا و عاشورا', 'mourning', 'mourning', 'hijri', 1, 9, 2, 7, true, null,
                'روزهای سوگواری و تعطیل رسمی. خیلی از کافه‌ها ساعت کاری را تغییر می‌دهند یا تعطیل‌اند.',
                ['ساعت کاری این دو روز را در «شعبه‌ها» تنظیم کنید', 'تم و تبلیغات شاد را متوقف کنید', 'موسیقی را آرام یا خاموش کنید', 'پیامک تبلیغاتی برای این روزها زمان‌بندی نکنید'],
                null),
            self::row('arbaeen', 'اربعین حسینی', 'mourning', 'mourning', 'hijri', 2, 20, 1, 5, true, null,
                'روز سوگواری و تعطیل رسمی.',
                ['ساعت کاری را بررسی کنید', 'تم و تبلیغات شاد را متوقف کنید'],
                null),
            self::row('end_of_safar', 'رحلت پیامبر (ص)، شهادت امام حسن (ع) و امام رضا (ع)', 'mourning', 'mourning', 'hijri', 2, 28, 3, 5, true, null,
                'روزهای سوگواری پایان صفر و تعطیل رسمی.',
                ['ساعت کاری را بررسی کنید', 'تم و تبلیغات شاد را متوقف کنید'],
                null),
            self::row('fatimiyya', 'شهادت حضرت فاطمه (س)', 'mourning', 'mourning', 'hijri', 6, 3, 1, 5, true, null,
                'روز سوگواری و تعطیل رسمی.',
                ['ساعت کاری را بررسی کنید', 'تم و تبلیغات شاد را متوقف کنید'],
                null),
            self::row('qadr_nights', 'شب‌های قدر و شهادت امام علی (ع)', 'mourning', 'mourning', 'hijri', 9, 19, 5, 5, true, null,
                'شب‌های احیا در ماه رمضان؛ ۲۱ رمضان تعطیل رسمی است.',
                ['ساعت کاری شب‌ها را با مراسم هماهنگ کنید', 'تبلیغات شاد را متوقف کنید', 'بسته‌ی سحری برای شب‌های احیا'],
                null),
            self::row('imam_sadegh', 'شهادت امام جعفر صادق (ع)', 'mourning', 'mourning', 'hijri', 10, 25, 1, 5, true, null,
                'روز سوگواری و تعطیل رسمی.',
                ['ساعت کاری را بررسی کنید', 'تم و تبلیغات شاد را متوقف کنید'],
                null),

            // ── Global ───────────────────────────────────────────────────────
            self::row('valentine', 'ولنتاین', 'global', 'festive', 'gregorian', 2, 14, 1, 10, false, null,
                'روز عشاق؛ قرارهای دونفره و هدیه.',
                ['منوی دونفره', 'دسر و نوشیدنی قرمز و صورتی', 'رزرو میز دونفره'],
                null),
            self::row('womens_day', 'روز جهانی زن', 'global', 'calm', 'gregorian', 3, 8, 1, 7, false, null,
                'روز جهانی زن.',
                ['هدیه‌ی کوچک کنار سفارش', 'پیشنهاد ویژه'],
                null),
            self::row('halloween', 'هالووین', 'global', 'festive', 'gregorian', 10, 31, 1, 10, false, 'autumn',
                'محبوب میان جوان‌ترها؛ تزئین و طعم‌های کدو حلوایی.',
                ['نوشیدنی و دسر کدو حلوایی', 'تزئین پاییزی'],
                null),
            self::row('black_friday', 'جمعه‌ی سیاه (بلک‌فرایدی)', 'global', 'festive', 'gregorian', 11, null, 3, 14, false, null,
                'فصل حراج؛ مشتری‌ها منتظر تخفیف‌اند.',
                ['تخفیف محدود با زمان پایان (شمارش معکوس روی منو)', 'کد تخفیف برای اعضای باشگاه', 'پیامک به مشتریانی که مدتی نیامده‌اند'],
                'فقط تا آخر هفته: تخفیف ویژه روی منو!', 'black_friday'),
            self::row('christmas_new_year', 'کریسمس و سال نو میلادی', 'global', 'festive', 'gregorian', 12, 25, 8, 14, false, 'winter',
                'حال‌وهوای زمستانی و جشن پایان سال میلادی.',
                ['نوشیدنی‌های زمستانی: دارچین، جینجربرد، شکلات داغ', 'تزئین زمستانی', 'جعبه‌ی هدیه'],
                null),

            // ── Drink and food days ──────────────────────────────────────────
            self::row('coffee_day', 'روز جهانی قهوه', 'drink_day', 'festive', 'gregorian', 10, 1, 1, 14, false, 'autumn',
                'روز قهوه! بهترین بهانه برای معرفی دانه‌ها و نوشیدنی‌های قهوه‌ای.',
                ['قهوه‌ی دوم با تخفیف', 'معرفی دانه‌ها و روش‌های دم‌آوری', 'تخفیف روی دانه‌ی قهوه‌ی بسته‌بندی'],
                'روز جهانی قهوه مبارک! امروز قهوه‌ی دوم مهمان ما.'),
            self::row('tea_day', 'روز جهانی چای', 'drink_day', 'festive', 'gregorian', 5, 21, 1, 10, false, null,
                'روز چای؛ چای‌های دمی و دمنوش‌ها را معرفی کنید.',
                ['چای‌های ویژه و دمنوش‌ها بالای منو', 'تخفیف روی چای‌ها'],
                null),
            self::row('chocolate_day', 'روز جهانی شکلات', 'drink_day', 'festive', 'gregorian', 7, 7, 1, 10, false, null,
                'روز شکلات؛ دسرها و نوشیدنی‌های شکلاتی.',
                ['نوشیدنی و دسر شکلاتی ویژه', 'باکس شکلات'],
                null),
        ];
    }

    /**
     * @param  list<string>  $ideas
     * @return array<string, mixed>
     */
    private static function row(
        string $key, string $title, string $kind, string $tone, string $calendar, int $month, ?int $day,
        int $duration, int $lead, bool $holiday, ?string $theme, string $description, array $ideas, ?string $campaign, ?string $rule = null,
    ): array {
        return [
            'key' => $key, 'title' => $title, 'description' => $description, 'kind' => $kind, 'tone' => $tone,
            'calendar' => $calendar, 'month' => $month, 'day' => $day, 'rule' => $rule, 'duration_days' => $duration,
            'lead_days' => $lead, 'is_holiday' => $holiday, 'ideas' => $ideas, 'campaign_text' => $campaign, 'theme' => $theme,
            'is_active' => true, 'is_one_off' => false,
        ];
    }
}
