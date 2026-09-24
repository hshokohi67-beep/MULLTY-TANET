'use client';

import { useActionState, useState } from 'react';
import { Badge, Button, Card, Checkbox, SelectField, TextField } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { deleteCategory, saveCategory } from '@/app/actions/catalog';
import { FormStatus } from '@/components/FormStatus';
import type { Category, FormState } from '@/lib/types';

type ParentOption = { id: string; name: string };

const initial: FormState = { ok: false };

export function CategoryForm({ parents }: { parents: ParentOption[] }) {
  const [state, action, pending] = useActionState(saveCategory.bind(null, null), initial);

  return (
    <Card className="p-4">
      <form action={action} key={state.ok ? state.message : 'form'} className="flex flex-col gap-3">
        <p className="text-sm font-semibold">دسته‌بندی جدید</p>
        <FormStatus state={state} />
        <div className="grid items-start gap-3 sm:grid-cols-[2fr_2fr_2fr_auto]">
          <TextField label="نام" name="name" required error={state.errors?.name} placeholder="مثلاً قهوه‌ی گرم" />
          <SelectField label="زیرمجموعه‌ی" name="parent_id" defaultValue="" error={state.errors?.parent_id}>
            <option value="">— سطح اول —</option>
            {parents.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </SelectField>
          <SelectField label="حس دما در منو" name="temperature" defaultValue="" hint="هاله‌ی گرم یا سرد پشت عکس محصولات این دسته">
            <option value="">خنثی</option>
            <option value="hot">گرم (نارنجی)</option>
            <option value="cold">سرد (آبی)</option>
          </SelectField>
          <Button type="submit" loading={pending} className="sm:mt-7">افزودن</Button>
        </div>
        <TextField label="عکس دسته (اختیاری)" name="image" type="file" accept="image/png,image/jpeg,image/webp" error={state.errors?.image}
          hint="در منوی آنلاین به‌صورت دایره کنار نام دسته دیده می‌شود؛ مربعی یا نزدیک به مربع بهتر است." />
      </form>
    </Card>
  );
}

export function CategoryRow({ category, depth, parents, readOnly }: { category: Category; depth: number; parents: ParentOption[]; readOnly: boolean }) {
  const [editing, setEditing] = useState(false);
  const [state, action, pending] = useActionState(saveCategory.bind(null, category.id), initial);
  const [deleteState, deleteAction, deleting] = useActionState(deleteCategory.bind(null, category.id), initial);

  if (editing && !readOnly) {
    return (
      <li className="px-4 py-3">
        <form action={action} className="flex flex-col gap-3">
          <FormStatus state={state} />
          <div className="grid items-start gap-3 sm:grid-cols-[2fr_2fr_2fr_1fr]">
            <TextField label="نام" name="name" defaultValue={category.name} required error={state.errors?.name} />
            <SelectField label="زیرمجموعه‌ی" name="parent_id" defaultValue={category.parent_id ?? ''} error={state.errors?.parent_id}>
              <option value="">— سطح اول —</option>
              {parents.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </SelectField>
            <SelectField label="حس دما در منو" name="temperature" defaultValue={category.temperature ?? ''} hint="هاله‌ی گرم یا سرد پشت عکس محصولات این دسته">
            <option value="">خنثی</option>
            <option value="hot">گرم (نارنجی)</option>
            <option value="cold">سرد (آبی)</option>
            </SelectField>
            <TextField label="ترتیب" name="sort" defaultValue={String(category.sort)} inputMode="numeric" ltr />
          </div>
          <div className="flex items-end gap-3">
            {category.image_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- tenant media
              <img src={category.image_url} alt="" className="size-14 shrink-0 rounded-full object-cover ring-2 ring-border" />
            ) : null}
            <div className="flex-1">
              <TextField label="عکس دسته" name="image" type="file" accept="image/png,image/jpeg,image/webp" error={state.errors?.image} hint="دایره‌ای در منوی آنلاین" />
            </div>
          </div>
          {category.image_url ? <Checkbox label="حذف عکس فعلی" name="remove_image" /> : null}
          <Checkbox label="فعال" name="is_active" defaultChecked={category.is_active} />
          <div className="flex gap-2">
            <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
            <Button size="sm" variant="ghost" onClick={() => setEditing(false)}>بستن</Button>
          </div>
        </form>
      </li>
    );
  }

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-3" style={{ paddingInlineStart: `${1 + depth * 1.5}rem` }}>
      {category.image_url ? (
        // eslint-disable-next-line @next/next/no-img-element -- tenant media
        <img src={category.image_url} alt="" className="size-8 rounded-full object-cover ring-1 ring-border" />
      ) : null}
      <span className="font-medium">{category.name}</span>
      {!category.is_active ? <Badge>غیرفعال</Badge> : null}
      {category.temperature ? <Badge tone={category.temperature === 'hot' ? 'warning' : 'info'} dot>{category.temperature === 'hot' ? 'گرم' : 'سرد'}</Badge> : null}
      <span className="text-xs text-text-muted">{formatNumber(category.products_count ?? 0)} آیتم</span>
      {deleteState.message ? <span role="status" className={deleteState.ok ? 'text-xs text-success' : 'text-xs text-danger'}>{deleteState.message}</span> : null}
      {!readOnly ? (
        <div className="ms-auto flex gap-1">
          <Button size="sm" variant="ghost" onClick={() => setEditing(true)}>ویرایش</Button>
          <form action={deleteAction}>
            <Button type="submit" size="sm" variant="ghost" loading={deleting} aria-label={`حذف دسته‌بندی ${category.name}`}>حذف</Button>
          </form>
        </div>
      ) : null}
    </li>
  );
}
