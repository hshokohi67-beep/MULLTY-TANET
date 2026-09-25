import 'server-only';
import { cache } from 'react';
import { redirect } from 'next/navigation';
import { api } from './api';
import type { BillingStatus } from './billing-types';

/** The café's subscription state and features, once per request (null if it can't be read). */
export const getBillingStatus = cache(async (): Promise<BillingStatus | null> => {
  try {
    return (await api<{ data: BillingStatus }>('/billing/status')).data;
  } catch {
    return null;
  }
});

/** Sends the user to the upgrade view when the plan doesn't include this screen's feature. */
export async function requireFeature(feature: string): Promise<void> {
  const status = await getBillingStatus();
  if (status && status.features[feature] === false) {
    redirect(`/dashboard/billing?feature=${encodeURIComponent(feature)}`);
  }
}

/** Whether the plan includes a feature (true when the status can't be read, so nothing breaks). */
export async function hasFeature(feature: string): Promise<boolean> {
  const status = await getBillingStatus();

  return !status || status.features[feature] !== false;
}
