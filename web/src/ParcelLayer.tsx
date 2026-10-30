import { useState } from "react";
import type { LatLngBounds, Layer } from "leaflet";
import type { Feature, Polygon } from "geojson";
import type { ParcelProperties } from "./types";
import { GeoJSON, useMap, useMapEvents } from "react-leaflet";
import { useParcels } from "./useParcels";

const MIN_ZOOM = 18;

function bindParcelPopup(
  feature: Feature<Polygon, ParcelProperties>,
  layer: Layer,
) {
  const { label, area, reference } = feature.properties;

  layer.bindPopup(
    `<strong>parc. ${label}</strong><br>` +
      `Výměra: ${area} m²<br>` +
      `Ref.: ${reference}`,
  );
}

export default function ParcelLayer() {
  const map = useMap();

  const currentBounds = () =>
    map.getZoom() >= MIN_ZOOM ? map.getBounds() : null;

  const [bounds, setBounds] = useState<LatLngBounds | null>(currentBounds);

  useMapEvents({
    moveend: () => setBounds(currentBounds()),
  });

  const { parcels, version, error } = useParcels(bounds);

  const message =
    bounds === null
      ? "Přibližte mapu pro zobrazení parcel"
      : error !== null
        ? "Parcely se nepodařilo načíst"
        : null;

  return (
    <>
      {message !== null && <div className="map-hint">{message}</div>}
      {parcels !== null && (
        <GeoJSON
          key={version}
          data={parcels}
          style={{ weight: 1, fillOpacity: 0.2 }}
          onEachFeature={bindParcelPopup}
        />
      )}
    </>
  );
}
