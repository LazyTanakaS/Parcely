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

match ($path) {
    "/" => print "hello",
    "/api/parcels" => sendJson(["bytes" => strlen(fetchParcelGml())]),
    default => sendJson(["error" => "Not found"], 404),
};
