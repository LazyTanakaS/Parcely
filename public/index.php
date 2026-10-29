<?php

declare(strict_types=1);
use App\JsonResponse;
use App\Bbox;
use App\CuzkClient;
use App\GmlParser;

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

function handleParcels(): void
{
    $bbox = Bbox::fromString($_GET["bbox"] ?? null);

    if ($bbox === null) {
        JsonResponse::send(
            ["error" => "Expected bbox=minLon,minLat,maxLon,maxLat"],
            400,
        );
        return;
    }

    $client = new CuzkClient();
    $parser = new GmlParser();

    try {
        $gml = $client->fetchParcelGml($bbox);
        $features = $parser->parse($gml);
    } catch (RuntimeException $e) {
        JsonResponse::send(["error" => "Cuzk did not return valid data"], 502);
        return;
    }

    JsonResponse::send([
        "type" => "FeatureCollection",
        "features" => $features,
    ]);
}

match ($path) {
    "/" => print "hello",
    "/api/parcels" => handleParcels(),
    default => JsonResponse::send(["error" => "Not found"], 404),
};
