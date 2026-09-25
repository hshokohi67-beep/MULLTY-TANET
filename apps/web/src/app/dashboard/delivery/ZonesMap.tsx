'use client';

import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from 'react';
import type { Map as LeafletMap } from 'leaflet';

const TILE_URL = process.env.NEXT_PUBLIC_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; OpenStreetMap';

/**
 * Read-only picture of a branch's delivery zones: the branch pin and each radius as a circle
 * (largest first, so smaller zones stay clickable on top). Colours come from the theme tokens.
 */
export function ZonesMap({ center, zones }: { center: { lat: number; lng: number }; zones: { id: string; name: string; radius_m: number; is_active: boolean }[] }) {
  const box = useRef<HTMLDivElement>(null);
  const map = useRef<LeafletMap | null>(null);

  useEffect(() => {
    let cancelled = false;
    void import('leaflet').then((L) => {
      if (cancelled || !box.current) return;
      map.current?.remove();
      const styles = getComputedStyle(document.documentElement);
      const brand = styles.getPropertyValue('--color-brand').trim() || 'currentColor';
      const muted = styles.getPropertyValue('--color-text-subtle').trim() || 'gray';
      const m = L.map(box.current, { center: [center.lat, center.lng], zoom: 13, scrollWheelZoom: false });
      L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(m);
      const icon = L.divIcon({ className: 'map-pin', html: '<span></span>', iconSize: [28, 40], iconAnchor: [14, 38] });
      L.marker([center.lat, center.lng], { icon, title: 'شعبه' }).addTo(m);

      const circles = [...zones].sort((a, b) => b.radius_m - a.radius_m).map((z) =>
        L.circle([center.lat, center.lng], { radius: z.radius_m, color: z.is_active ? brand : muted, weight: 2, fillOpacity: z.is_active ? 0.08 : 0.03, dashArray: z.is_active ? undefined : '6 6' })
          .bindTooltip(`${z.name}${z.is_active ? '' : ' (غیرفعال)'}`, { direction: 'top' })
          .addTo(m));
      if (circles.length) m.fitBounds(L.featureGroup(circles).getBounds(), { padding: [16, 16] });
      map.current = m;
    });

    return () => {
      cancelled = true;
      map.current?.remove();
      map.current = null;
    };
  }, [center.lat, center.lng, zones]);

  return <div ref={box} className="h-64 w-full overflow-hidden rounded-xl border border-border bg-surface-muted" role="img" aria-label={`نقشه‌ی ${zones.length} محدوده‌ی ارسال دور شعبه`} />;
}
