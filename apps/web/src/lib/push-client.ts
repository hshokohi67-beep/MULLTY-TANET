/**
 * Browser side of Web Push: is it possible here, and subscribe/unsubscribe this browser. The
 * subscription goes to the API through a Server Action (the browser never calls the API).
 */
export interface BrowserSubscription { endpoint: string; keys: { p256dh: string; auth: string } }

export type PushSupport = 'ok' | 'unsupported' | 'ios-needs-install' | 'denied';

export function pushSupport(): PushSupport {
  if (typeof window === 'undefined') return 'unsupported';
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const installed = window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true;
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    return ios && !installed ? 'ios-needs-install' : 'unsupported';
  }
  if (Notification.permission === 'denied') return 'denied';

  return 'ok';
}

function keyBytes(base64url: string): Uint8Array<ArrayBuffer> {
  const padded = base64url.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (base64url.length % 4)) % 4);
  const raw = atob(padded);
  const out = new Uint8Array(new ArrayBuffer(raw.length));
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);

  return out;
}

async function registration(): Promise<ServiceWorkerRegistration> {
  return (await navigator.serviceWorker.getRegistration('/')) ?? navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

/** The current subscription of this browser, if any. */
export async function currentSubscription(): Promise<BrowserSubscription | null> {
  if (pushSupport() !== 'ok') return null;
  const sub = await (await registration()).pushManager.getSubscription();

  return sub ? (sub.toJSON() as BrowserSubscription) : null;
}

/** Asks for permission (must follow a tap) and subscribes; null when the visitor says no. */
export async function subscribeBrowser(publicKey: string): Promise<BrowserSubscription | null> {
  if (pushSupport() !== 'ok') return null;
  if ((await Notification.requestPermission()) !== 'granted') return null;
  const reg = await registration();
  await navigator.serviceWorker.ready;
  const sub = (await reg.pushManager.getSubscription()) ?? await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });

  return sub.toJSON() as BrowserSubscription;
}
