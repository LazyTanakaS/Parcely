<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class CuzkClient
{
    private const WFS_URL = "https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp";

    public function fetchParcelGml(Bbox $bbox): string
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

        $gml = file_get_contents($url);

        if ($gml === false) {
            throw new RuntimeException("CUZK service is not available");
        }

        return $gml;
    }
}
