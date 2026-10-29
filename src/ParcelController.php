<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class ParcelController
{
    public function __construct(
        private readonly CuzkClient $client,
        private readonly GmlParser $parser,
    ) {}

    public function index(): void
    {
        $bbox = Bbox::fromString($_GET["bbox"] ?? null);

        if ($bbox === null) {
            JsonResponse::send(
                ["error" => "Expected bbox=minLon,minLat,maxLon,maxLat"],
                400,
            );
            return;
        }

        try {
            $gml = $this->client->fetchParcelGml($bbox);
            $features = $this->parser->parse($gml);
        } catch (RuntimeException $e) {
            JsonResponse::send(
                ["error" => "Cuzk did not return valid data"],
                502,
            );
            return;
        }

        JsonResponse::send([
            "type" => "FeatureCollection",
            "features" => $features,
        ]);
    }
}
