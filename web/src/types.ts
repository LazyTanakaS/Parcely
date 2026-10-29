import type { FeatureCollection, Polygon } from "geojson";

export type ParcelProperties = {
  label: string;
  area: number;
  reference: string;
};

export type ParcelCollection = FeatureCollection<Polygon, ParcelProperties>;
