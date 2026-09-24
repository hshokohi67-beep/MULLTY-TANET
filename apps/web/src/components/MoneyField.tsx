'use client';

import { useState } from 'react';
import { TextField, type TextFieldProps } from '@cafe/ui';
import { formatMoney } from '@cafe/locale';
import { parseTomanInput } from '@/lib/money';

/**
 * Toman input. Accepts Persian or Latin digits with or without separators and shows
 * the formatted amount as a hint, so "850000" vs "85000" mistakes are obvious before saving.
 */
export function MoneyField({ defaultValue, hint, ...rest }: Omit<TextFieldProps, 'type' | 'inputMode'> & { defaultValue?: string }) {
  const [value, setValue] = useState(defaultValue ?? '');
  const rials = parseTomanInput(value);
  const preview = rials === null ? null : Number.isNaN(rials) ? 'فقط عدد وارد کنید' : formatMoney(rials);

  return (
    <TextField
      {...rest}
      inputMode="numeric"
      ltr
      value={value}
      onChange={(e) => setValue(e.target.value)}
      hint={preview ?? hint ?? 'به تومان'}
    />
  );
}
