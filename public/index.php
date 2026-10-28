<?php

declare(strict_types=1);

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
}

function fetchParcelGml(): string
{
    $url =
        "https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp?" .
        http_build_query([
            "service" => "WFS",
            "version" => "2.0.0",
            "request" => "GetFeature",
            "typeNames" => "cp:CadastralParcel",
            "srsName" => "urn:ogc:def:crs:EPSG::4326",
            "bbox" => "50.436,15.350,50.438,15.353,urn:ogc:def:crs:EPSG::4326",
        ]);

    $gml = file_get_contents($url);

    return $gml;
}

function parseParcels(string $gml): array
{
    $xml = simplexml_load_string($gml);
    $xml->registerXPathNamespace(
        "cp",
        "http://inspire.ec.europa.eu/schemas/cp/4.0",
    );

    $result = [];

    foreach ($xml->xpath("//cp:CadastralParcel") as $parcel) {
        $result[] = [
            "type" => "Feature",
            "geometry" => [
                "type" => "Polygon",
                "coordinates" => parcelRings($parcel),
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

function parsePosList(string $posList): array
{
    $flat = explode(" ", trim($posList));
    $pairs = array_chunk($flat, 2);

    return array_map(
        fn(array $pair) => [(float) $pair[1], (float) $pair[0]],
        $pairs,
    );
}

function parcelRings(SimpleXMLElement $parcel): array
{
    $parcel->registerXPathNamespace("gml", "http://www.opengis.net/gml/3.2");

    $outer = $parcel->xpath(".//gml:exterior//gml:posList");
    $holes = $parcel->xpath(".//gml:interior//gml:posList");

    $rings = [parsePosList((string) $outer[0])];

    foreach ($holes as $hole) {
        $rings[] = parsePosList((string) $hole[0]);
    }

    return $rings;
}

match ($path) {
    "/" => print "hello",
    "/api/parcels" => sendJson([
        "type" => "FeatureCollection",
        "features" => parseParcels(fetchParcelGml()),
    ]),
    default => sendJson(["error" => "Not found"], 404),
};
