import { useState } from "react";
import type { LatLngBounds } from "leaflet";
import { GeoJSON, useMap, useMapEvents } from "react-leaflet";
import { useParcels } from "./useParcels";

const MIN_ZOOM = 18;

export default function ParcelLayer() {
  const map = useMap();

  const currentBounds = () =>
    map.getZoom() >= MIN_ZOOM ? map.getBounds() : null;

  const [bounds, setBounds] = useState<LatLngBounds | null>(currentBounds);

  useMapEvents({
    moveend: () => setBounds(currentBounds()),
  });

  const { parcels, version } = useParcels(bounds);

  if (parcels === null) {
    return null;
  }

  return (
    <GeoJSON
      key={version}
      data={parcels}
      style={{ weight: 1, fillOpacity: 0.2 }}
    />
  );
}
