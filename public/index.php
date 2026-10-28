<?php

declare(strict_types=1);

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
}

match ($path) {
    "/" => print "hello",
    "/api/parcels" => sendJson([]),
    default => sendJson(["error" => "Not found"], 404),
};
