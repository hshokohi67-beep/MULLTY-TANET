'use client';

import { useState } from 'react';

/** A fresh idempotency key per submission; a retry (double click) of the same submission reuses it. */
export function useSubmissionKey(): [string, () => void] {
  const [key, setKey] = useState(() => crypto.randomUUID());

  return [key, () => setKey(crypto.randomUUID())];
}
