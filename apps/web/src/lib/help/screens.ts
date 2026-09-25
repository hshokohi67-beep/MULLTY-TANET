/** Screen path → help topic lookup, safe for client bundles (no help text inside). */
export function topicForPath(map: Record<string, string>, pathname: string): string | null {
  let best: string | null = null;
  for (const screen of Object.keys(map)) {
    const hit = pathname === screen || pathname.startsWith(`${screen}/`);
    if (hit && (best === null || screen.length > best.length)) best = screen;
  }

  return best ? map[best] : null;
}
