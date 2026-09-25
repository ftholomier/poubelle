'use client';

import { useState } from 'react';
import { MapView } from '@/components/maps/MapView';

/** Choix d'une position par glisser-déposer du repère ; les coordonnées partent avec le formulaire. */
export function PositionPicker({
  lat,
  lng,
  tileUrl,
  attribution,
  label = 'Position sur la carte',
}: {
  lat: number;
  lng: number;
  tileUrl: string;
  attribution: string;
  label?: string;
}) {
  const [pos, setPos] = useState({ lat, lng });
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      <span style={{ fontSize: 13, fontWeight: 600 }}>{label}</span>
      <input type="hidden" name="lat" value={pos.lat.toFixed(6)} />
      <input type="hidden" name="lng" value={pos.lng.toFixed(6)} />
      <div style={{ height: 220, borderRadius: 12, overflow: 'hidden', border: '1px solid var(--line)' }}>
        <MapView
          mode="picker"
          center={[lat, lng]}
          zoom={15}
          tileUrl={tileUrl}
          attribution={attribution}
          onPick={(la, ln) => setPos({ lat: la, lng: ln })}
          ariaLabel="Déplacez le repère pour indiquer la position"
          style={{ width: '100%', height: '100%' }}
        />
      </div>
      <span style={{ fontSize: 12, color: 'var(--muted)' }}>
        Déplacez le repère · {pos.lat.toFixed(5)}, {pos.lng.toFixed(5)}
      </span>
    </div>
  );
}
