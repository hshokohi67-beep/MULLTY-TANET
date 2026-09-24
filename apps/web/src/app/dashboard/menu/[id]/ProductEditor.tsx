'use client';

import Link from 'next/link';
import { useActionState, useState, useTransition } from 'react';
import { Button, Card, CardHeader, Checkbox, EmptyState, SelectField, TextAreaField, TextField } from '@cafe/ui';
import { formatMoney, toPersianDigits } from '@cafe/locale';
import {
  deleteProduct,
  deleteProductImage,
  saveBranchPrices,
  saveProductDetails,
  saveProductModifierGroups,
  saveVariants,
  uploadProductImage,
} from '@/app/actions/catalog';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import { DIETARY_TAGS, type Branch, type Category, type FormState, type ModifierGroup, type Product } from '@/lib/types';

const initial: FormState = { ok: false };

export function ProductDetailsForm({ product, categories, readOnly }: { product: Product; categories: Category[]; readOnly: boolean }) {
  const [state, action, pending] = useActionState(saveProductDetails.bind(null, product.id), initial);
  const e = state.errors ?? {};
  const selected = new Set(product.categories?.map((c) => c.id));
  const tags = new Set(product.dietary_tags);

  return (
    <Card>
      <CardHeader title="مشخصات" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <TextField label="نام" name="name" defaultValue={product.name} required error={e.name} />
          <TextField label="ترتیب نمایش" name="sort" defaultValue={String(product.sort)} inputMode="numeric" ltr error={e.sort} hint="عدد کوچک‌تر بالاتر نمایش داده می‌شود." />
          <div className="sm:col-span-2">
            <TextAreaField label="توضیحات" name="description" defaultValue={product.description ?? ''} error={e.description} hint="مواد اصلی و ویژگی‌ها؛ در منوی آنلاین و جستجو استفاده می‌شود." />
          </div>

          <fieldset className="sm:col-span-2">
            <legend className="mb-2 text-sm font-medium">دسته‌بندی‌ها</legend>
            {categories.length === 0 ? (
              <p className="text-sm text-text-muted">هنوز دسته‌بندی ندارید. <Link href="/dashboard/menu/categories" className="text-brand hover:underline">ساخت دسته‌بندی</Link></p>
            ) : (
              <div className="flex flex-wrap gap-2">
                {categories.map((c) => (
                  <label key={c.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="checkbox" name="category_ids" value={c.id} defaultChecked={selected.has(c.id)} className="accent-[var(--color-brand)]" />
                    {c.name}
                  </label>
                ))}
              </div>
            )}
            {e['category_ids.0'] ? <p role="alert" className="mt-1 text-xs text-danger">{e['category_ids.0']}</p> : null}
          </fieldset>

          <fieldset className="sm:col-span-2">
            <legend className="mb-2 text-sm font-medium">برچسب‌های رژیمی و حساسیت</legend>
            <div className="flex flex-wrap gap-2">
              {Object.entries(DIETARY_TAGS).map(([key, label]) => (
                <label key={key} className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                  <input type="checkbox" name="dietary_tags" value={key} defaultChecked={tags.has(key)} className="accent-[var(--color-brand)]" />
                  {label}
                </label>
              ))}
            </div>
          </fieldset>

          <TextField label="کالری" name="nutrition.calories" defaultValue={product.nutrition?.calories != null ? String(product.nutrition.calories) : ''} inputMode="numeric" ltr error={e['nutrition.calories']} />
          <TextField label="کافئین (میلی‌گرم)" name="nutrition.caffeine_mg" defaultValue={product.nutrition?.caffeine_mg != null ? String(product.nutrition.caffeine_mg) : ''} inputMode="numeric" ltr error={e['nutrition.caffeine_mg']} />

          <SelectField label="حس دما در منو" name="temperature" defaultValue={product.temperature ?? ''} hint="خالی = مثل دسته‌بندی محصول">
            <option value="">مثل دسته‌بندی</option>
            <option value="hot">گرم (نارنجی)</option>
            <option value="cold">سرد (آبی)</option>
          </SelectField>

          <Checkbox label="فعال (در منوی آنلاین نمایش داده شود)" name="is_active" defaultChecked={product.is_active} />
          <Checkbox label="ویژه (بالای منو برجسته شود)" name="is_featured" defaultChecked={product.is_featured} />
        </fieldset>
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره‌ی مشخصات</Button></div> : null}
      </form>
    </Card>
  );
}

interface VariantRow {
  key: string;
  id: string;
  name: string;
  price: string;
}

export function VariantsForm({ product, readOnly }: { product: Product; readOnly: boolean }) {
  const [state, action, pending] = useActionState(saveVariants.bind(null, product.id), initial);
  const [rows, setRows] = useState<VariantRow[]>(() =>
    (product.variants ?? []).map((v) => ({ key: v.id, id: v.id, name: v.name ?? '', price: rialToTomanInput(v.base_price) })),
  );
  const e = state.errors ?? {};
  const multiple = rows.length > 1;

  return (
    <Card>
      <CardHeader title="سایزها و قیمت" description="برای آیتم تک‌سایز، نام سایز را خالی بگذارید. قیمت‌ها به تومان است." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="flex flex-col gap-3">
          <legend className="sr-only">سایزها</legend>
          {rows.map((row, i) => (
            <div key={row.key} className="grid items-start gap-3 sm:grid-cols-[1fr_1fr_auto]">
              <input type="hidden" name="variant_id" value={row.id} />
              <TextField
                label={`نام سایز ${toPersianDigits(i + 1)}`}
                name="variant_name"
                value={row.name}
                onChange={(ev) => setRows(rows.map((r) => (r.key === row.key ? { ...r, name: ev.target.value } : r)))}
                placeholder={multiple ? 'مثلاً کوچک' : 'اختیاری'}
                required={multiple}
                error={e[`variants.${i}.name`]}
              />
              <MoneyField key={`${row.key}-price`} label="قیمت (تومان)" name="variant_price" defaultValue={row.price} required error={e[`variants.${i}.base_price`]} />
              {rows.length > 1 && !readOnly ? (
                <Button variant="ghost" size="sm" className="sm:mt-7" onClick={() => setRows(rows.filter((r) => r.key !== row.key))} aria-label={`حذف سایز ${row.name || toPersianDigits(i + 1)}`}>
                  حذف
                </Button>
              ) : <span />}
            </div>
          ))}
        </fieldset>
        {!readOnly ? (
          <div className="flex flex-wrap gap-2">
            {rows.length < 10 ? (
              <Button variant="secondary" onClick={() => setRows([...rows, { key: crypto.randomUUID(), id: '', name: '', price: '' }])}>افزودن سایز</Button>
            ) : null}
            <Button type="submit" loading={pending}>ذخیره‌ی سایزها و قیمت</Button>
          </div>
        ) : null}
      </form>
    </Card>
  );
}

export function BranchPricesForm({ product, branches }: { product: Product; branches: Branch[] }) {
  const [branchId, setBranchId] = useState(branches[0]?.id ?? '');
  const [state, action, pending] = useActionState(saveBranchPrices.bind(null, product.id, branchId), initial);

  return (
    <Card>
      <CardHeader title="قیمت متفاوت در شعبه‌ها" description="فقط اگر قیمت این آیتم در یک شعبه فرق دارد پر کنید؛ خانه‌ی خالی یعنی قیمت پایه." />
      <form action={action} key={branchId} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <div className="max-w-xs">
          <SelectField label="شعبه" value={branchId} onChange={(ev) => setBranchId(ev.target.value)}>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
        </div>
        <fieldset disabled={pending} className="grid gap-3 sm:grid-cols-2">
          <legend className="sr-only">قیمت‌های شعبه</legend>
          {(product.variants ?? []).map((v) => {
            const override = v.branch_prices.find((p) => p.branch_id === branchId);

            return (
              <div key={v.id}>
                <input type="hidden" name="variant_id" value={v.id} />
                <MoneyField
                  label={v.name ?? product.name}
                  name="amount"
                  defaultValue={rialToTomanInput(override?.amount)}
                  hint={v.base_price != null ? `قیمت پایه: ${formatMoney(v.base_price)}` : undefined}
                />
              </div>
            );
          })}
        </fieldset>
        <div><Button type="submit" loading={pending}>ذخیره‌ی قیمت‌های این شعبه</Button></div>
      </form>
    </Card>
  );
}

export function ModifierGroupsForm({ product, groups, readOnly }: { product: Product; groups: ModifierGroup[]; readOnly: boolean }) {
  const [state, action, pending] = useActionState(saveProductModifierGroups.bind(null, product.id), initial);
  const attached = new Set(product.modifier_groups?.map((g) => g.id));

  return (
    <Card>
      <CardHeader title="افزودنی‌ها و انتخاب‌ها" description="مثل نوع شیر یا شات اضافه؛ مشتری هنگام سفارش انتخاب می‌کند." />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        {groups.length === 0 ? (
          <EmptyState title="گروه افزودنی ندارید" description="اول یک گروه (مثلاً «نوع شیر») بسازید." action={<Link href="/dashboard/menu/modifiers" className="text-sm font-medium text-brand hover:underline">ساخت گروه افزودنی</Link>} />
        ) : (
          <fieldset disabled={readOnly || pending} className="flex flex-wrap gap-2">
            <legend className="sr-only">گروه‌های افزودنی</legend>
            {groups.map((g) => (
              <label key={g.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="modifier_group_ids" value={g.id} defaultChecked={attached.has(g.id)} className="accent-[var(--color-brand)]" />
                {g.name}
                {g.is_required ? <span className="text-xs text-text-muted">(اجباری)</span> : null}
              </label>
            ))}
          </fieldset>
        )}
        {!readOnly && groups.length > 0 ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

export function ImagesManager({ product, readOnly }: { product: Product; readOnly: boolean }) {
  const [state, action, pending] = useActionState(uploadProductImage.bind(null, product.id), initial);
  const [deleting, startDelete] = useTransition();
  const images = product.images ?? [];

  return (
    <Card>
      <CardHeader title="تصاویر" description="تصویر اول، تصویر اصلی آیتم در منو است. حداکثر ۶ تصویر." />
      <div className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        {images.length > 0 ? (
          <ul className="flex flex-wrap gap-3">
            {images.map((img, i) => (
              <li key={img.id} className="relative">
                {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
                <img src={img.url} alt={img.alt ?? ''} className="size-24 rounded-md border border-border object-cover" />
                {i === 0 ? <span className="absolute start-1 top-1 rounded bg-brand px-1.5 text-[10px] text-on-brand">اصلی</span> : null}
                {!readOnly ? (
                  <button
                    type="button"
                    disabled={deleting}
                    onClick={() => startDelete(() => deleteProductImage(product.id, img.id))}
                    className="mt-1 block w-full text-xs text-danger hover:underline"
                  >
                    حذف
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-text-muted">هنوز تصویری ندارد. آیتم‌های تصویردار در منو بیشتر دیده می‌شوند.</p>
        )}
        {!readOnly && images.length < 6 ? (
          <form action={action} className="flex flex-wrap items-end gap-3" key={images.length}>
            <div className="flex-1">
              <TextField label="افزودن تصویر" name="image" type="file" accept="image/png,image/jpeg,image/webp" error={state.errors?.image} hint="PNG، JPG یا WebP، حداکثر ۲ مگابایت، دست‌کم ۲۰۰×۲۰۰ پیکسل" />
            </div>
            <Button type="submit" loading={pending} variant="secondary">بارگذاری</Button>
          </form>
        ) : null}
      </div>
    </Card>
  );
}

export function DeleteProductButton({ productId, name }: { productId: string; name: string }) {
  const [confirming, setConfirming] = useState(false);
  const [pending, start] = useTransition();

  if (!confirming) {
    return <Button variant="ghost" onClick={() => setConfirming(true)}>حذف آیتم</Button>;
  }

  return (
    <div role="alertdialog" aria-label="تأیید حذف" className="flex items-center gap-2 rounded-md bg-danger-soft px-3 py-1.5 text-sm text-danger">
      <span>«{name}» از منو حذف شود؟ سفارش‌های قبلی آسیبی نمی‌بینند.</span>
      <Button variant="danger" size="sm" loading={pending} onClick={() => start(() => deleteProduct(productId))}>بله، حذف شود</Button>
      <Button variant="ghost" size="sm" onClick={() => setConfirming(false)}>انصراف</Button>
    </div>
  );
}
