<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class CuzkClient
{
    private const WFS_URL = "https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp";
    private const GRID_STEP_DEG = 0.002;
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(private readonly string $cacheDir) {}

    public function fetchParcelGml(Bbox $bbox): string
    {
        $snapped = $bbox->snapped(self::GRID_STEP_DEG);
        $file = $this->cacheDir . "/" . md5($snapped->toCuzkParam()) . ".gml";

        if (
            is_file($file) &&
            time() - filemtime($file) < self::CACHE_TTL_SECONDS
        ) {
            return (string) file_get_contents($file);
        }

        try {
            $gml = $this->download($snapped);
        } catch (RuntimeException $e) {
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }

            throw $e;
        }

        $this->store($file, $gml);
        return $gml;
    }

    private function download(Bbox $bbox): string
    {
        $url =
            self::WFS_URL .
            "?" .
            http_build_query([
                "service" => "WFS",
                "version" => "2.0.0",
                "request" => "GetFeature",
                "typeNames" => "cp:CadastralParcel",
                "srsName" => "urn:ogc:def:crs:EPSG::4326",
                "bbox" => $bbox->toCuzkParam(),
            ]);

        $context = stream_context_create(["http" => ["timeout" => 10]]);
        $gml = @file_get_contents($url, false, $context);

        if ($gml === false) {
            throw new RuntimeException("CUZK service is not available");
        }

        return $gml;
    }

    private function store(string $file, string $gml): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $tmp = $file . "." . uniqid("", true) . ".tmp";
        file_put_contents($tmp, $gml);
        rename($tmp, $file);
    }
}
