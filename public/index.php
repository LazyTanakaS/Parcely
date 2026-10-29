<?php

declare(strict_types=1);
const MAX_BBOX_SIZE_DEG = 0.02;
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
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

function parseBbox(?string $raw): ?array
{
    if ($raw === null) {
        return null;
    }

    $parts = explode(",", $raw);

    if (count($parts) !== 4) {
        return null;
    }

    foreach ($parts as $part) {
        if (!is_numeric($part)) {
            return null;
        }
    }

    [$minLon, $minLat, $maxLon, $maxLat] = array_map("floatval", $parts);

    if ($minLon >= $maxLon || $minLat >= $maxLat) {
        return null;
    }

    if (
        $maxLon - $minLon > MAX_BBOX_SIZE_DEG ||
        $maxLat - $minLat > MAX_BBOX_SIZE_DEG
    ) {
        return null;
    }

    return [$minLat, $minLon, $maxLat, $maxLon];
}

function fetchParcelGml(array $bbox): string
{
    $url =
        "https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp?" .
        http_build_query([
            "service" => "WFS",
            "version" => "2.0.0",
            "request" => "GetFeature",
            "typeNames" => "cp:CadastralParcel",
            "srsName" => "urn:ogc:def:crs:EPSG::4326",
            "bbox" => implode(",", $bbox),
        ]);

    $gml = file_get_contents($url);

    return $gml;
}

function handleParcels(): void
{
    $bbox = parseBbox($_GET["bbox"] ?? null);

    if ($bbox === null) {
        sendJson(["error" => "Expected bbox=minLon,minLat,maxLon,maxLat"], 400);
        return;
    }

    sendJson([
        "type" => "FeatureCollection",
        "features" => parseParcels(fetchParcelGml($bbox)),
    ]);
}

match ($path) {
    "/" => print "hello",
    "/api/parcels" => handleParcels(),
    default => sendJson(["error" => "Not found"], 404),
};
