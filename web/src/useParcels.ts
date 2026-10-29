import { useEffect, useState } from "react";
import type { LatLngBounds } from "leaflet";
import type { ParcelCollection } from "./types";

export function useParcels(bounds: LatLngBounds | null) {
  const [parcels, setParcels] = useState<ParcelCollection | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [version, setVersion] = useState(0);

  useEffect(() => {
    if (bounds === null) {
      return;
    }

    const controller = new AbortController();

    const timer = setTimeout(() => {
      const bbox = [
        bounds.getWest(),
        bounds.getSouth(),
        bounds.getEast(),
        bounds.getNorth(),
      ].join(",");

      fetch(`/api/parcels?bbox=${bbox}`, { signal: controller.signal })
        .then((res) => {
          if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
          }
          return res.json() as Promise<ParcelCollection>;
        })
        .then((data) => {
          setParcels(data);
          setVersion((v) => v + 1);
          setError(null);
        })
        .catch((err: Error) => {
          if (err.name !== "AbortError") {
            setError(err.message);
          }
        });
    }, 250);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [bounds]);

  return { parcels: bounds === null ? null : parcels, error, version };
}
