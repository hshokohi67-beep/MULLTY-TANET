import { Alert } from '@cafe/ui';
import type { FormState } from '@/lib/types';

/** Success or error banner for a server-action form. */
export function FormStatus({ state }: { state: FormState }) {
  if (!state.message) return null;

  return <Alert tone={state.ok ? 'success' : 'danger'}>{state.message}</Alert>;
}
