<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use SimpleXMLElement;

final class GmlParser
{
    private const NS_CP = "http://inspire.ec.europa.eu/schemas/cp/4.0";
    private const NS_GML = "http://www.opengis.net/gml/3.2";

    /** @return list<array<string, mixed>> GeoJSON features */
    public function parse(string $gml): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($gml);

        if ($xml === false) {
            throw new RuntimeException("Invalid GML from CUZK");
        }

        $xml->registerXPathNamespace("cp", self::NS_CP);

        $result = [];

        foreach ($xml->xpath("//cp:CadastralParcel") as $parcel) {
            $result[] = [
                "type" => "Feature",
                "geometry" => [
                    "type" => "Polygon",
                    "coordinates" => $this->parcelRings($parcel),
                ],
                "properties" => [
                    "label" => (string) $parcel->xpath("cp:label")[0],
                    "area" => (int) $parcel->xpath("cp:areaValue")[0],
                    "reference" => (string) $parcel->xpath(
                        "cp:nationalCadastralReference",
                    )[0],
                ],
            ];
        }

        return $result;
    }

    private function parseRing(string $posList): array
    {
        $flat = explode(" ", trim($posList));
        $pairs = array_chunk($flat, 2);

        return array_map(
            fn(array $pair) => [(float) $pair[1], (float) $pair[0]],
            $pairs,
        );
    }

    private function parcelRings(SimpleXMLElement $parcel): array
    {
        $parcel->registerXPathNamespace("gml", self::NS_GML);

        $outer = $parcel->xpath(".//gml:exterior//gml:posList");
        $holes = $parcel->xpath(".//gml:interior//gml:posList");

        $rings = [$this->parseRing((string) $outer[0])];

        foreach ($holes as $hole) {
            $rings[] = $this->parseRing((string) $hole[0]);
        }

        return $rings;
    }
}
