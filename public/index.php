<?php

declare(strict_types=1);
use App\JsonResponse;
use App\Bbox;
use App\CuzkClient;

spl_autoload_register(function (string $class): void {
    $prefix = "App\\";

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . "/../src/" . str_replace("\\", "/", $relative) . ".php";

    if (is_file($file)) {
        require $file;
    }
});

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

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

function handleParcels(): void
{
    $bbox = Bbox::fromString($_GET["bbox"] ?? null);

    if ($bbox === null) {
        JsonResponse::send(["error" => "Expected bbox=..."], 400);
        return;
    }

    $client = new CuzkClient();

    try {
        $gml = $client->fetchParcelGml($bbox);
    } catch (RuntimeException $e) {
        JsonResponse::send(["error" => "Cuzk is not available now"], 502);
        return;
    }

    JsonResponse::send([
        "type" => "FeatureCollection",
        "features" => parseParcels($gml),
    ]);
}

match ($path) {
    "/" => print "hello",
    "/api/parcels" => handleParcels(),
    default => JsonResponse::send(["error" => "Not found"], 404),
};
