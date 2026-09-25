import { redirect } from 'next/navigation';
import { getStaffToken } from '@/lib/session';

/** Staff land in their panel; everyone else in the public marketplace. */
export default async function Home() {
  redirect((await getStaffToken()) ? '/dashboard' : '/explore');
}
