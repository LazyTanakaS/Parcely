<?php

declare(strict_types=1);
use App\JsonResponse;
use App\CuzkClient;
use App\GmlParser;
use App\ParcelController;

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

match ($path) {
    "/" => print "hello",
    "/api/parcels" => new ParcelController(
        new CuzkClient(),
        new GmlParser(),
    )->index(),
    default => JsonResponse::send(["error" => "Not found"], 404),
};
