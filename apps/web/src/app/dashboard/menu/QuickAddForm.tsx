'use client';

import Link from 'next/link';
import { useActionState } from 'react';
import { Alert, Button, Card, SelectField, TextField } from '@cafe/ui';
import { quickAddProduct, type QuickAddState } from '@/app/actions/catalog';
import { MoneyField } from '@/components/MoneyField';
import type { Category } from '@/lib/types';

/** Quick Add: name + price (+ category) puts an item on the menu; details can come later. */
export function QuickAddForm({ categories }: { categories: Category[] }) {
  const [state, action, pending] = useActionState<QuickAddState, FormData>(quickAddProduct, { ok: false });
  const e = state.errors ?? {};

  return (
    <Card className="p-4">
      <form action={action} key={state.created ?? 'form'} className="flex flex-col gap-3">
        <p className="text-sm font-semibold">افزودن سریع آیتم</p>
        {state.message ? (
          <Alert
            tone={state.ok ? 'success' : 'danger'}
            action={state.created ? <Link href={`/dashboard/menu/${state.created}`} className="text-sm font-medium underline">تکمیل جزئیات</Link> : undefined}
          >
            {state.message}
          </Alert>
        ) : null}
        <div className="grid items-start gap-3 sm:grid-cols-[2fr_1fr_1fr_auto]">
          <TextField label="نام" name="name" required placeholder="مثلاً لاته وانیلی" error={e.name} />
          <MoneyField label="قیمت (تومان)" name="price" required placeholder="۸۵۰۰۰" error={e.price} />
          <SelectField label="دسته‌بندی" name="category_id" defaultValue="" error={e.category_id}>
            <option value="">بدون دسته</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </SelectField>
          <Button type="submit" loading={pending} className="sm:mt-7">افزودن</Button>
        </div>
      </form>
    </Card>
  );
}
