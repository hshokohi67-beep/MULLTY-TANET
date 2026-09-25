import type { Tone } from '@cafe/ui';
import type { PurchaseOrder } from '@/lib/inventory-types';

export const STATUS_TONE: Record<PurchaseOrder['status'], Tone> = { draft: 'neutral', ordered: 'info', received: 'success', cancelled: 'danger' };
