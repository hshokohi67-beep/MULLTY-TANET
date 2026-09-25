'use client';

import 'leaflet/dist/leaflet.css';
import { useEffect, useRef, useState } from 'react';
import type { Map as LeafletMap, Marker } from 'leaflet';
import { LocateFixed } from 'lucide-react';

// Tiles are configurable: OSM by default; an Iranian provider (Neshan, Map.ir) can be set per deployment.
const TILE_URL = process.env.NEXT_PUBLIC_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; OpenStreetMap';

export interface Point { lat: number; lng: number }

/**
 * Pick a point (a delivery address, a branch): tap the map or drag the pin, or use the phone's location. Leaflet loads
 * only in the browser; if tiles are unreachable the pin still works and "my location" still fills it.
 */
export function MapPicker({ value, center, onChange, label = 'نقشه: برای انتخاب محل تحویل روی نقشه بزنید یا نشانگر را بکشید', pinTitle = 'محل تحویل', height = 'h-64' }: {
  value: Point | null; center: Point; onChange: (p: Point) => void; label?: string; pinTitle?: string; height?: string;
}) {
  const box = useRef<HTMLDivElement>(null);
  const map = useRef<LeafletMap | null>(null);
  const marker = useRef<Marker | null>(null);
  const change = useRef(onChange);
  const [locating, setLocating] = useState(false);
  const [geoError, setGeoError] = useState<string | null>(null);

  useEffect(() => { change.current = onChange; }, [onChange]);

  useEffect(() => {
    let cancelled = false;
    void import('leaflet').then((L) => {
      if (cancelled || !box.current || map.current) return;
      const start = value ?? center;
      const m = L.map(box.current, { center: [start.lat, start.lng], zoom: value ? 16 : 14, zoomControl: true, attributionControl: true });
      L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(m);
      const icon = L.divIcon({ className: 'map-pin', html: '<span></span>', iconSize: [28, 40], iconAnchor: [14, 38] });
      const pin = L.marker([start.lat, start.lng], { draggable: true, icon, keyboard: true, title: pinTitle });
      if (value) pin.addTo(m);
      pin.on('dragend', () => { const p = pin.getLatLng(); change.current({ lat: p.lat, lng: p.lng }); });
      m.on('click', (e) => {
        pin.setLatLng(e.latlng);
        if (!m.hasLayer(pin)) pin.addTo(m);
        change.current({ lat: e.latlng.lat, lng: e.latlng.lng });
      });
      map.current = m;
      marker.current = pin;
    });

    return () => {
      cancelled = true;
      map.current?.remove();
      map.current = null;
    };
    // The map is created once; later value changes move the pin below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const locate = () => {
    if (!navigator.geolocation) {
      setGeoError('مرورگر شما موقعیت‌یابی ندارد.');
      return;
    }
    setLocating(true);
    setGeoError(null);
    navigator.geolocation.getCurrentPosition((pos) => {
      setLocating(false);
      const p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      if (map.current && marker.current) {
        marker.current.setLatLng([p.lat, p.lng]);
        if (!map.current.hasLayer(marker.current)) marker.current.addTo(map.current);
        map.current.setView([p.lat, p.lng], 17);
      }
      change.current(p);
    }, () => {
      setLocating(false);
      setGeoError('موقعیت شما پیدا نشد؛ روی نقشه بزنید.');
    }, { enableHighAccuracy: true, timeout: 10000 });
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="relative overflow-hidden rounded-xl border border-border">
        <div ref={box} className={`${height} w-full bg-surface-muted`} role="application" aria-label={label} />
        <button type="button" onClick={locate} disabled={locating}
          className="absolute bottom-3 start-3 z-[500] inline-flex h-10 items-center gap-2 rounded-full bg-surface px-3 text-sm font-medium shadow-[var(--shadow-md)] hover:bg-surface-muted disabled:opacity-60">
          <LocateFixed className="size-4 text-brand" aria-hidden="true" />{locating ? 'در حال یافتن…' : 'موقعیت من'}
        </button>
      </div>
      {geoError ? <p className="text-xs text-warning">{geoError}</p> : null}
    </div>
  );
}
