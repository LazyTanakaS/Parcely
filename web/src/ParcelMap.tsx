import { MapContainer, TileLayer } from "react-leaflet";
import "leaflet/dist/leaflet.css";
import ParcelsLayer from "./ParcelLayer";

export default function ParcelMap() {
  return (
    <MapContainer
      center={[50.437, 15.352]}
      zoom={18}
      style={{ height: "100%", width: "100%" }}
    >
      <TileLayer
        url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution="&copy; OpenStreetMap contributors"
        maxZoom={19}
      />
      <ParcelsLayer />
    </MapContainer>
  );
}
